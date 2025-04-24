<?php
class AB_Testing {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function create_test($post_id, $variations) {
        $test_id = uniqid('ab_test_');
        $test_data = array(
            'test_id' => $test_id,
            'post_id' => $post_id,
            'variations' => $variations,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'results' => array()
        );

        $tests = get_option('turknews_twitter_ab_tests', array());
        $tests[$test_id] = $test_data;
        update_option('turknews_twitter_ab_tests', $tests);

        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - AB Test Oluşturuldu: ' . print_r($test_data, true));
        }

        return $test_id;
    }

    public function get_test($test_id) {
        $tests = get_option('turknews_twitter_ab_tests', array());
        return $tests[$test_id] ?? false;
    }

    public function update_test_results($test_id, $variation_id, $engagement_data) {
        $tests = get_option('turknews_twitter_ab_tests', array());
        
        if (!isset($tests[$test_id])) {
            return false;
        }

        if (!isset($tests[$test_id]['results'][$variation_id])) {
            $tests[$test_id]['results'][$variation_id] = array(
                'tweet_id' => $engagement_data['tweet_id'],
                'likes' => $engagement_data['likes'],
                'retweets' => $engagement_data['retweets'],
                'replies' => $engagement_data['replies'],
                'quotes' => $engagement_data['quotes'],
                'impressions' => $engagement_data['impressions'],
                'engagement_rate' => $this->calculate_engagement_rate($engagement_data),
                'updated_at' => current_time('mysql')
            );
        } else {
            $tests[$test_id]['results'][$variation_id]['likes'] = $engagement_data['likes'];
            $tests[$test_id]['results'][$variation_id]['retweets'] = $engagement_data['retweets'];
            $tests[$test_id]['results'][$variation_id]['replies'] = $engagement_data['replies'];
            $tests[$test_id]['results'][$variation_id]['quotes'] = $engagement_data['quotes'];
            $tests[$test_id]['results'][$variation_id]['impressions'] = $engagement_data['impressions'];
            $tests[$test_id]['results'][$variation_id]['engagement_rate'] = $this->calculate_engagement_rate($engagement_data);
            $tests[$test_id]['results'][$variation_id]['updated_at'] = current_time('mysql');
        }

        update_option('turknews_twitter_ab_tests', $tests);

        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - AB Test Sonuçları Güncellendi: ' . print_r($tests[$test_id], true));
        }

        return true;
    }

    private function calculate_engagement_rate($engagement_data) {
        $total_engagement = $engagement_data['likes'] + $engagement_data['retweets'] + 
                          $engagement_data['replies'] + $engagement_data['quotes'];
        
        if ($engagement_data['impressions'] > 0) {
            return ($total_engagement / $engagement_data['impressions']) * 100;
        }
        
        return 0;
    }

    public function get_winning_variation($test_id) {
        $test = $this->get_test($test_id);
        
        if (!$test || empty($test['results'])) {
            return false;
        }

        $winning_variation = null;
        $highest_engagement = 0;

        foreach ($test['results'] as $variation_id => $results) {
            if ($results['engagement_rate'] > $highest_engagement) {
                $highest_engagement = $results['engagement_rate'];
                $winning_variation = $variation_id;
            }
        }

        return $winning_variation;
    }

    public function generate_test_report($test_id) {
        $test = $this->get_test($test_id);
        
        if (!$test) {
            return '<p>Test bulunamadı.</p>';
        }

        $html = '<div class="ab-test-report">';
        $html .= '<h2>AB Test Raporu</h2>';
        $html .= '<p>Test ID: ' . $test_id . '</p>';
        $html .= '<p>Oluşturulma Tarihi: ' . $test['created_at'] . '</p>';
        
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr><th>Varyasyon</th><th>Beğeni</th><th>Retweet</th><th>Yanıt</th><th>Alıntı</th><th>Görüntülenme</th><th>Etkileşim Oranı</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($test['results'] as $variation_id => $results) {
            $html .= '<tr>';
            $html .= '<td>' . $variation_id . '</td>';
            $html .= '<td>' . $results['likes'] . '</td>';
            $html .= '<td>' . $results['retweets'] . '</td>';
            $html .= '<td>' . $results['replies'] . '</td>';
            $html .= '<td>' . $results['quotes'] . '</td>';
            $html .= '<td>' . $results['impressions'] . '</td>';
            $html .= '<td>%' . number_format($results['engagement_rate'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $winning_variation = $this->get_winning_variation($test_id);
        if ($winning_variation) {
            $html .= '<p><strong>Kazanan Varyasyon:</strong> ' . $winning_variation . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    public function end_test($test_id) {
        $tests = get_option('turknews_twitter_ab_tests', array());
        
        if (!isset($tests[$test_id])) {
            return false;
        }

        $tests[$test_id]['status'] = 'completed';
        $tests[$test_id]['completed_at'] = current_time('mysql');
        
        update_option('turknews_twitter_ab_tests', $tests);

        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - AB Test Tamamlandı: ' . $test_id);
        }

        return true;
    }

    public function get_active_tests() {
        $tests = get_option('turknews_twitter_ab_tests', array());
        return array_filter($tests, function($test) {
            return $test['status'] === 'active';
        });
    }

    public function get_completed_tests() {
        $tests = get_option('turknews_twitter_ab_tests', array());
        return array_filter($tests, function($test) {
            return $test['status'] === 'completed';
        });
    }
} 