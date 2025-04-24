<?php
class Scheduler {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
        
        // Zamanlanmış görevleri kontrol et
        add_action('turknews_twitter_scheduled_check', array($this, 'check_scheduled_tweets'));
        
        // Zamanlanmış görevleri başlat
        if (!wp_next_scheduled('turknews_twitter_scheduled_check')) {
            wp_schedule_event(time(), 'hourly', 'turknews_twitter_scheduled_check');
        }
    }

    public function schedule_tweet($post_id, $scheduled_time) {
        $scheduled_tweets = get_option('turknews_twitter_scheduled_tweets', array());
        
        $scheduled_tweets[$post_id] = array(
            'scheduled_time' => $scheduled_time,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        update_option('turknews_twitter_scheduled_tweets', $scheduled_tweets);
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Tweet Zamanlandı: ' . print_r($scheduled_tweets[$post_id], true));
        }
    }

    public function check_scheduled_tweets() {
        $scheduled_tweets = get_option('turknews_twitter_scheduled_tweets', array());
        $current_time = current_time('mysql');
        
        foreach ($scheduled_tweets as $post_id => $tweet_data) {
            if ($tweet_data['status'] === 'pending' && strtotime($tweet_data['scheduled_time']) <= strtotime($current_time)) {
                $this->send_scheduled_tweet($post_id, $tweet_data);
            }
        }
    }

    public function get_scheduled_tweets() {
        return get_option('turknews_twitter_scheduled_tweets', array());
    }

    public function cancel_scheduled_tweet($post_id) {
        $scheduled_tweets = get_option('turknews_twitter_scheduled_tweets', array());
        
        if (isset($scheduled_tweets[$post_id])) {
            unset($scheduled_tweets[$post_id]);
            update_option('turknews_twitter_scheduled_tweets', $scheduled_tweets);
            
            if ($this->debug_mode) {
                error_log('TurkNews Twitter Auto - Tweet Zamanlaması İptal Edildi: ' . $post_id);
            }
        }
    }

    public function get_best_time_to_post($post_id) {
        // En iyi paylaşım zamanını hesapla
        // Şimdilik rastgele bir zaman döndür
        $hours = array(9, 12, 15, 18, 21);
        $hour = $hours[array_rand($hours)];
        
        $scheduled_time = strtotime('today ' . $hour . ':00');
        if ($scheduled_time < time()) {
            $scheduled_time = strtotime('tomorrow ' . $hour . ':00');
        }
        
        return date('Y-m-d H:i:s', $scheduled_time);
    }

    public function process_scheduled_tweets() {
        $scheduled_tweets = get_option('turknews_twitter_scheduled_tweets', array());
        $current_time = current_time('mysql');
        
        foreach ($scheduled_tweets as $post_id => $tweet_data) {
            if ($tweet_data['status'] === 'pending' && strtotime($tweet_data['scheduled_time']) <= strtotime($current_time)) {
                $this->send_scheduled_tweet($post_id, $tweet_data);
            }
        }
    }

    private function send_scheduled_tweet($post_id, $tweet_data) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            $this->update_tweet_status($post_id, 'failed');
            return;
        }

        $content_optimizer = new Content_Optimizer();
        $optimized_content = $content_optimizer->optimize_content($post);
        
        $multi_account = new Multi_Account();
        $results = $multi_account->send_tweet_to_accounts($optimized_content);
        
        $success_count = count(array_filter($results, function($result) {
            return $result['success'];
        }));
        
        if ($success_count > 0) {
            $this->update_tweet_status($post_id, 'published');
            update_post_meta($post_id, '_tweeted', true);
        } else {
            $this->update_tweet_status($post_id, 'failed');
        }
    }

    private function update_tweet_status($post_id, $status) {
        $scheduled_tweets = get_option('turknews_twitter_scheduled_tweets', array());
        
        if (isset($scheduled_tweets[$post_id])) {
            $scheduled_tweets[$post_id]['status'] = $status;
            $scheduled_tweets[$post_id]['updated_at'] = current_time('mysql');
            update_option('turknews_twitter_scheduled_tweets', $scheduled_tweets);
        }
    }
} 