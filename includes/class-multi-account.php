<?php
class Multi_Account {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function add_account($account_data) {
        $accounts = get_option('turknews_twitter_accounts', array());
        
        $account_id = uniqid('acc_');
        $accounts[$account_id] = array(
            'username' => $account_data['username'],
            'auth_token' => $account_data['auth_token'],
            'csrf_token' => $account_data['csrf_token'],
            'is_active' => true,
            'created_at' => current_time('mysql')
        );
        
        update_option('turknews_twitter_accounts', $accounts);
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Yeni Hesap Eklendi: ' . print_r($accounts[$account_id], true));
        }
        
        return $account_id;
    }

    public function delete_account($account_id) {
        $accounts = get_option('turknews_twitter_accounts', array());
        
        if (isset($accounts[$account_id])) {
            unset($accounts[$account_id]);
            update_option('turknews_twitter_accounts', $accounts);
            
            if ($this->debug_mode) {
                error_log('TurkNews Twitter Auto - Hesap Silindi: ' . $account_id);
            }
            
            return true;
        }
        
        return false;
    }

    public function get_accounts() {
        $accounts = get_option('turknews_twitter_accounts', array());
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Hesaplar Alındı: ' . print_r($accounts, true));
        }
        
        return $accounts;
    }

    public function get_active_accounts() {
        $accounts = $this->get_accounts();
        $active_accounts = array_filter($accounts, function($account) {
            return isset($account['is_active']) && $account['is_active'] === true;
        });
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Aktif Hesaplar: ' . print_r($active_accounts, true));
        }
        
        return $active_accounts;
    }

    public function send_tweet_to_accounts($tweet_data) {
        $accounts = $this->get_active_accounts();
        $results = array();
        
        foreach ($accounts as $account_id => $account) {
            $twitter_api = new Twitter_API();
            $twitter_api->set_tokens($account['auth_token'], $account['csrf_token']);
            
            $result = $twitter_api->post_tweet($tweet_data['tweet_text'], $tweet_data['image_url']);
            $results[$account_id] = $result;
            
            $this->update_account_stats($account_id, $result);
            
            if ($this->debug_mode) {
                error_log('TurkNews Twitter Auto - Tweet Gönderildi: ' . print_r($result, true));
            }
        }
        
        return $results;
    }

    public function update_account_stats($account_id, $tweet_result) {
        $stats = get_option('turknews_twitter_account_stats', array());
        
        if (!isset($stats[$account_id])) {
            $stats[$account_id] = array(
                'total_tweets' => 0,
                'successful_tweets' => 0,
                'failed_tweets' => 0,
                'last_tweet_time' => '',
                'last_tweet_id' => ''
            );
        }
        
        $stats[$account_id]['total_tweets']++;
        
        if ($tweet_result['success']) {
            $stats[$account_id]['successful_tweets']++;
            $stats[$account_id]['last_tweet_time'] = current_time('mysql');
            $stats[$account_id]['last_tweet_id'] = $tweet_result['response']['data']['create_tweet']['tweet_results']['result']['rest_id'] ?? '';
        } else {
            $stats[$account_id]['failed_tweets']++;
        }
        
        update_option('turknews_twitter_account_stats', $stats);
    }

    public function get_account_stats($account_id) {
        $stats = get_option('turknews_twitter_account_stats', array());
        return $stats[$account_id] ?? array(
            'total_tweets' => 0,
            'successful_tweets' => 0,
            'failed_tweets' => 0,
            'last_tweet_time' => '',
            'last_tweet_id' => ''
        );
    }
} 