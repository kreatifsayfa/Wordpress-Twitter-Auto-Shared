<?php
class Engagement {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function track_engagement($tweet_id, $account_id) {
        $twitter_api = new Twitter_API();
        $tweet_data = $twitter_api->get_tweet_engagement($tweet_id);
        
        if (!$tweet_data) {
            return false;
        }
        
        $engagement_data = array(
            'tweet_id' => $tweet_id,
            'account_id' => $account_id,
            'likes' => $tweet_data['favorite_count'],
            'retweets' => $tweet_data['retweet_count'],
            'replies' => $tweet_data['reply_count'],
            'quotes' => $tweet_data['quote_count'],
            'impressions' => $tweet_data['impression_count'],
            'tracked_at' => current_time('mysql')
        );
        
        $this->save_engagement_data($engagement_data);
        
        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - Etkileşim Verisi: ' . print_r($engagement_data, true));
        }
        
        return $engagement_data;
    }

    private function save_engagement_data($engagement_data) {
        $engagement_history = get_option('turknews_twitter_engagement_history', array());
        
        if (!isset($engagement_history[$engagement_data['tweet_id']])) {
            $engagement_history[$engagement_data['tweet_id']] = array();
        }
        
        $engagement_history[$engagement_data['tweet_id']][] = $engagement_data;
        update_option('turknews_twitter_engagement_history', $engagement_history);
    }

    public function get_engagement_history($tweet_id) {
        $engagement_history = get_option('turknews_twitter_engagement_history', array());
        return $engagement_history[$tweet_id] ?? array();
    }

    public function generate_engagement_report($account_id) {
        $engagement_history = get_option('turknews_twitter_engagement_history', array());
        $account_engagement = array();
        
        foreach ($engagement_history as $tweet_id => $history) {
            foreach ($history as $entry) {
                if ($entry['account_id'] === $account_id) {
                    $account_engagement[] = $entry;
                }
            }
        }
        
        if (empty($account_engagement)) {
            return '<p>Henüz etkileşim verisi bulunmuyor.</p>';
        }
        
        $html = '<div class="engagement-report">';
        $html .= '<h2>Etkileşim Raporu</h2>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr><th>Tweet ID</th><th>Beğeni</th><th>Retweet</th><th>Yanıt</th><th>Alıntı</th><th>Görüntülenme</th><th>Tarih</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($account_engagement as $engagement) {
            $html .= '<tr>';
            $html .= '<td>' . $engagement['tweet_id'] . '</td>';
            $html .= '<td>' . $engagement['likes'] . '</td>';
            $html .= '<td>' . $engagement['retweets'] . '</td>';
            $html .= '<td>' . $engagement['replies'] . '</td>';
            $html .= '<td>' . $engagement['quotes'] . '</td>';
            $html .= '<td>' . $engagement['impressions'] . '</td>';
            $html .= '<td>' . $engagement['tracked_at'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        return $html;
    }

    public function get_engagement_stats($account_id) {
        $engagement_history = get_option('turknews_twitter_engagement_history', array());
        $stats = array(
            'total_tweets' => 0,
            'total_likes' => 0,
            'total_retweets' => 0,
            'total_replies' => 0,
            'total_quotes' => 0,
            'total_impressions' => 0,
            'average_engagement_rate' => 0
        );
        
        foreach ($engagement_history as $tweet_id => $history) {
            foreach ($history as $entry) {
                if ($entry['account_id'] === $account_id) {
                    $stats['total_tweets']++;
                    $stats['total_likes'] += $entry['likes'];
                    $stats['total_retweets'] += $entry['retweets'];
                    $stats['total_replies'] += $entry['replies'];
                    $stats['total_quotes'] += $entry['quotes'];
                    $stats['total_impressions'] += $entry['impressions'];
                }
            }
        }
        
        if ($stats['total_tweets'] > 0) {
            $total_engagement = $stats['total_likes'] + $stats['total_retweets'] + $stats['total_replies'] + $stats['total_quotes'];
            $stats['average_engagement_rate'] = ($total_engagement / $stats['total_impressions']) * 100;
        }
        
        return $stats;
    }
} 