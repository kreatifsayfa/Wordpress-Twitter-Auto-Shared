<?php
class Analytics {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function track_tweet($post_id, $tweet_data, $result) {
        $tweet_stats = array(
            'post_id' => $post_id,
            'tweet_text' => $tweet_data['text'],
            'has_image' => !empty($tweet_data['image_url']),
            'timestamp' => current_time('mysql'),
            'success' => $result['success'],
            'http_code' => $result['http_code']
        );

        // İstatistikleri kaydet
        $this->save_stats($tweet_stats);

        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Tweet İstatistikleri: ' . print_r($tweet_stats, true));
        }

        return $tweet_stats;
    }

    private function save_stats($stats) {
        $current_stats = get_option('turknews_twitter_stats', array());
        
        // Günlük istatistikleri güncelle
        $date = date('Y-m-d');
        if (!isset($current_stats[$date])) {
            $current_stats[$date] = array(
                'total_tweets' => 0,
                'successful_tweets' => 0,
                'failed_tweets' => 0,
                'tweets_with_images' => 0,
                'average_engagement' => 0
            );
        }

        $current_stats[$date]['total_tweets']++;
        if ($stats['success']) {
            $current_stats[$date]['successful_tweets']++;
        } else {
            $current_stats[$date]['failed_tweets']++;
        }
        if ($stats['has_image']) {
            $current_stats[$date]['tweets_with_images']++;
        }

        // İstatistikleri güncelle
        update_option('turknews_twitter_stats', $current_stats);
    }

    public function get_daily_report($days = 7) {
        $stats = get_option('turknews_twitter_stats', array());
        $report = array(
            'period' => $days . ' günlük rapor',
            'total_tweets' => 0,
            'successful_tweets' => 0,
            'failed_tweets' => 0,
            'tweets_with_images' => 0,
            'success_rate' => 0,
            'daily_stats' => array()
        );

        $dates = array_keys($stats);
        rsort($dates);
        $dates = array_slice($dates, 0, $days);

        foreach ($dates as $date) {
            $daily = $stats[$date];
            $report['total_tweets'] += $daily['total_tweets'];
            $report['successful_tweets'] += $daily['successful_tweets'];
            $report['failed_tweets'] += $daily['failed_tweets'];
            $report['tweets_with_images'] += $daily['tweets_with_images'];
            $report['daily_stats'][$date] = $daily;
        }

        if ($report['total_tweets'] > 0) {
            $report['success_rate'] = ($report['successful_tweets'] / $report['total_tweets']) * 100;
        }

        return $report;
    }

    public function get_engagement_report() {
        $stats = get_option('turknews_twitter_stats', array());
        $report = array(
            'total_tweets' => 0,
            'tweets_with_images' => 0,
            'image_usage_rate' => 0,
            'success_rate' => 0,
            'daily_engagement' => array()
        );

        foreach ($stats as $date => $daily) {
            $report['total_tweets'] += $daily['total_tweets'];
            $report['tweets_with_images'] += $daily['tweets_with_images'];
            $report['daily_engagement'][$date] = array(
                'total' => $daily['total_tweets'],
                'successful' => $daily['successful_tweets'],
                'with_images' => $daily['tweets_with_images']
            );
        }

        if ($report['total_tweets'] > 0) {
            $report['image_usage_rate'] = ($report['tweets_with_images'] / $report['total_tweets']) * 100;
            $report['success_rate'] = ($report['successful_tweets'] / $report['total_tweets']) * 100;
        }

        return $report;
    }

    public function get_category_report() {
        $stats = get_option('turknews_twitter_stats', array());
        $categories = array();
        
        foreach ($stats as $date => $daily) {
            $posts = get_posts(array(
                'date_query' => array(
                    array(
                        'year' => date('Y', strtotime($date)),
                        'month' => date('m', strtotime($date)),
                        'day' => date('d', strtotime($date))
                    )
                )
            ));

            foreach ($posts as $post) {
                $post_categories = get_the_category($post->ID);
                foreach ($post_categories as $category) {
                    if (!isset($categories[$category->name])) {
                        $categories[$category->name] = 0;
                    }
                    $categories[$category->name]++;
                }
            }
        }

        arsort($categories);

        return array(
            'total_categories' => count($categories),
            'categories' => $categories
        );
    }

    public function generate_html_report() {
        $daily_report = $this->get_daily_report();
        $engagement_report = $this->get_engagement_report();
        $category_report = $this->get_category_report();

        $html = '<div class="turknews-twitter-report">';
        $html .= '<h2>Twitter Raporu</h2>';
        
        // Genel İstatistikler
        $html .= '<h3>Genel İstatistikler</h3>';
        $html .= '<ul>';
        $html .= '<li>Toplam Tweet: ' . $engagement_report['total_tweets'] . '</li>';
        $html .= '<li>Başarılı Tweet: ' . $engagement_report['successful_tweets'] . '</li>';
        $html .= '<li>Başarı Oranı: %' . number_format($engagement_report['success_rate'], 2) . '</li>';
        $html .= '<li>Görsel Kullanım Oranı: %' . number_format($engagement_report['image_usage_rate'], 2) . '</li>';
        $html .= '</ul>';

        // Kategori Dağılımı
        $html .= '<h3>Kategori Dağılımı</h3>';
        $html .= '<ul>';
        foreach ($category_report['categories'] as $category => $count) {
            $html .= '<li>' . $category . ': ' . $count . ' tweet</li>';
        }
        $html .= '</ul>';

        // Günlük İstatistikler
        $html .= '<h3>Günlük İstatistikler</h3>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr><th>Tarih</th><th>Toplam</th><th>Başarılı</th><th>Başarısız</th><th>Görselli</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($daily_report['daily_stats'] as $date => $stats) {
            $html .= '<tr>';
            $html .= '<td>' . $date . '</td>';
            $html .= '<td>' . $stats['total_tweets'] . '</td>';
            $html .= '<td>' . $stats['successful_tweets'] . '</td>';
            $html .= '<td>' . $stats['failed_tweets'] . '</td>';
            $html .= '<td>' . $stats['tweets_with_images'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        $html .= '</div>';

        return $html;
    }
} 