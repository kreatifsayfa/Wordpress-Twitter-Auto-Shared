<?php
/**
 * Gelişmiş Hata Yönetimi Sınıfı
 */
class Error_Manager {
    private $debug_mode;
    private $max_retries;
    private $retry_delay;
    private $error_logs;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        $this->max_retries = isset($options['max_retries']) ? intval($options['max_retries']) : 3;
        $this->retry_delay = isset($options['retry_delay']) ? intval($options['retry_delay']) : 60;
        $this->error_logs = get_option('turknews_twitter_error_logs', array());
    }

    public function handle_error($error_code, $error_message, $context = array()) {
        $error_data = array(
            'code' => $error_code,
            'message' => $error_message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'retry_count' => 0
        );

        // Hata kaydını oluştur
        $error_id = uniqid('error_');
        $this->error_logs[$error_id] = $error_data;
        update_option('turknews_twitter_error_logs', $this->error_logs);

        // Debug modunda hata mesajını logla
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Error: ' . $error_message);
        }

        return $error_id;
    }

    public function retry_error($error_id, $callback) {
        if (!isset($this->error_logs[$error_id])) {
            return false;
        }

        $error = $this->error_logs[$error_id];
        
        if ($error['retry_count'] >= $this->max_retries) {
            $this->mark_error_as_failed($error_id);
            return false;
        }

        // Yeniden deneme sayısını artır
        $error['retry_count']++;
        $this->error_logs[$error_id] = $error;
        update_option('turknews_twitter_error_logs', $this->error_logs);

        // Belirtilen süre kadar bekle
        sleep($this->retry_delay);

        // Callback fonksiyonunu çağır
        try {
            $result = call_user_func($callback, $error['context']);
            if ($result) {
                $this->mark_error_as_resolved($error_id);
                return true;
            }
        } catch (Exception $e) {
            $this->handle_error('retry_failed', $e->getMessage(), $error['context']);
        }

        return false;
    }

    public function mark_error_as_resolved($error_id) {
        if (isset($this->error_logs[$error_id])) {
            $this->error_logs[$error_id]['status'] = 'resolved';
            $this->error_logs[$error_id]['resolved_at'] = current_time('mysql');
            update_option('turknews_twitter_error_logs', $this->error_logs);
        }
    }

    public function mark_error_as_failed($error_id) {
        if (isset($this->error_logs[$error_id])) {
            $this->error_logs[$error_id]['status'] = 'failed';
            $this->error_logs[$error_id]['failed_at'] = current_time('mysql');
            update_option('turknews_twitter_error_logs', $this->error_logs);
        }
    }

    public function get_error_logs($status = null, $limit = 50) {
        $logs = $this->error_logs;
        
        if ($status) {
            $logs = array_filter($logs, function($log) use ($status) {
                return isset($log['status']) && $log['status'] === $status;
            });
        }

        // Tarihe göre sırala
        uasort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($logs, 0, $limit, true);
    }

    public function clear_old_logs($days = 30) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $this->error_logs = array_filter($this->error_logs, function($log) use ($cutoff_date) {
            return strtotime($log['timestamp']) >= strtotime($cutoff_date);
        });

        update_option('turknews_twitter_error_logs', $this->error_logs);
    }

    public function get_error_stats() {
        $stats = array(
            'total' => count($this->error_logs),
            'resolved' => 0,
            'failed' => 0,
            'pending' => 0,
            'by_code' => array()
        );

        foreach ($this->error_logs as $error) {
            if (isset($error['status'])) {
                $stats[$error['status']]++;
            } else {
                $stats['pending']++;
            }

            if (!isset($stats['by_code'][$error['code']])) {
                $stats['by_code'][$error['code']] = 0;
            }
            $stats['by_code'][$error['code']]++;
        }

        return $stats;
    }

    public function generate_error_report() {
        $stats = $this->get_error_stats();
        $recent_errors = $this->get_error_logs(null, 10);

        $html = '<div class="error-report">';
        $html .= '<h2>Hata Raporu</h2>';
        
        // İstatistikler
        $html .= '<div class="error-stats">';
        $html .= '<h3>Genel İstatistikler</h3>';
        $html .= '<ul>';
        $html .= '<li>Toplam Hata: ' . $stats['total'] . '</li>';
        $html .= '<li>Çözülen: ' . $stats['resolved'] . '</li>';
        $html .= '<li>Başarısız: ' . $stats['failed'] . '</li>';
        $html .= '<li>Bekleyen: ' . $stats['pending'] . '</li>';
        $html .= '</ul>';

        // Hata kodlarına göre dağılım
        $html .= '<h3>Hata Kodlarına Göre Dağılım</h3>';
        $html .= '<ul>';
        foreach ($stats['by_code'] as $code => $count) {
            $html .= '<li>' . $code . ': ' . $count . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';

        // Son hatalar
        $html .= '<div class="recent-errors">';
        $html .= '<h3>Son Hatalar</h3>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr><th>Tarih</th><th>Kod</th><th>Mesaj</th><th>Durum</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($recent_errors as $error_id => $error) {
            $html .= '<tr>';
            $html .= '<td>' . $error['timestamp'] . '</td>';
            $html .= '<td>' . $error['code'] . '</td>';
            $html .= '<td>' . $error['message'] . '</td>';
            $html .= '<td>' . (isset($error['status']) ? $error['status'] : 'pending') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
} 