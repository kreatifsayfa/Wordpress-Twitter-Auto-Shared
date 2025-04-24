<?php
class Tweet_Retweeter {
    private $debug_mode;
    private $target_profiles;
    private $retweet_settings;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
        $this->target_profiles = get_option('turknews_twitter_target_profiles', array());
        $this->retweet_settings = get_option('turknews_twitter_retweet_settings', array(
            'auto_share' => 'no',
            'check_interval' => 60, // dakika
            'include_media' => 'yes',
            'max_tweets_per_day' => 10,
            'add_source' => 'yes' // Kaynak profili ekleme
        ));
    }

    public function add_target_profile($profile_data) {
        $profile_id = uniqid('profile_');
        
        $this->target_profiles[$profile_id] = array(
            'id' => $profile_id,
            'username' => $profile_data['username'],
            'last_checked' => null,
            'last_tweet_id' => null,
            'is_active' => true,
            'created_at' => current_time('mysql')
        );
        
        update_option('turknews_twitter_target_profiles', $this->target_profiles);
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Hedef Profil Eklendi: ' . $profile_data['username']);
        }
        
        return $profile_id;
    }

    public function get_target_profiles() {
        return $this->target_profiles;
    }

    public function get_active_profiles() {
        return array_filter($this->target_profiles, function($profile) {
            return $profile['is_active'];
        });
    }

    public function check_target_profiles() {
        $active_profiles = $this->get_active_profiles();
        $current_time = time();
        
        foreach ($active_profiles as $profile_id => $profile) {
            // Son kontrol zamanını kontrol et
            if ($profile['last_checked'] && 
                (strtotime($profile['last_checked']) + ($this->retweet_settings['check_interval'] * 60)) > $current_time) {
                continue;
            }
            
            // Profilin son tweetlerini al
            $tweets = $this->get_profile_tweets($profile['username'], $profile['last_tweet_id']);
            
            if (!empty($tweets)) {
                foreach ($tweets as $tweet) {
                    $this->share_tweet($tweet, $profile['username']);
                }
                
                // Son tweet ID'sini güncelle
                $this->target_profiles[$profile_id]['last_tweet_id'] = $tweets[0]['id'];
            }
            
            // Son kontrol zamanını güncelle
            $this->target_profiles[$profile_id]['last_checked'] = current_time('mysql');
        }
        
        update_option('turknews_twitter_target_profiles', $this->target_profiles);
    }

    private function get_profile_tweets($username, $since_id = null) {
        $twitter_api = new Twitter_API();
        $tweets = $twitter_api->get_user_tweets($username, $since_id);
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Profil Tweetleri Alındı: ' . $username);
        }
        
        return $tweets;
    }

    private function share_tweet($tweet, $source_username) {
        // Tweet metnini hazırla
        $tweet_text = $tweet['text'];
        
        // Kaynak profili ekle
        if ($this->retweet_settings['add_source'] === 'yes') {
            $tweet_text .= "\n\nKaynak: @" . $source_username;
        }
        
        $tweet_data = array(
            'text' => $tweet_text,
            'media_urls' => isset($tweet['media']) ? $tweet['media'] : array()
        );
        
        $multi_account = new Multi_Account();
        $results = $multi_account->send_tweet_to_accounts($tweet_data);
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Tweet Paylaşıldı: ' . print_r($results, true));
        }
        
        return $results;
    }

    public function update_retweet_settings($settings) {
        $this->retweet_settings = array_merge($this->retweet_settings, $settings);
        update_option('turknews_twitter_retweet_settings', $this->retweet_settings);
        
        return true;
    }

    public function get_retweet_settings() {
        return $this->retweet_settings;
    }
} 