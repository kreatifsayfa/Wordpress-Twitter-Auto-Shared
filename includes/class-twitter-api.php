<?php
class Twitter_API {
    private $auth_token;
    private $csrf_token;
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->auth_token = $options['auth_token'];
        $this->csrf_token = $options['csrf_token'];
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function post_tweet($message, $image_url = '') {
        $url = 'https://twitter.com/i/api/graphql/SoVnbfCycZ7fERGCwpZkYA/CreateTweet';
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer AAAAAAAAAAAAAAAAAAAAANRILgAAAAAAnNwIzUejRCOuH5E6I8xnZz4puTs%3D1Zv7ttfk8LF81IUq16cHjhLTvJu4FA33AGWWjCpTnA',
            'x-csrf-token' => $this->csrf_token,
            'x-twitter-auth-type' => 'OAuth2Session',
            'x-twitter-active-user' => 'yes',
            'x-twitter-client-language' => 'tr',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'referer' => 'https://twitter.com/compose/tweet',
            'origin' => 'https://twitter.com'
        );

        $cookies = array(
            'auth_token' => $this->auth_token,
            'ct0' => $this->csrf_token
        );

        $variables = array(
            'tweet_text' => $message,
            'dark_request' => false,
            'media' => array(
                'media_entities' => array(),
                'possibly_sensitive' => false
            ),
            'semantic_annotation_ids' => array()
        );

        // Eğer görsel varsa, medya yükleme işlemi yap
        if (!empty($image_url)) {
            $media_result = $this->upload_media($image_url);
            if ($media_result['success']) {
                $variables['media']['media_entities'][] = array(
                    'media_id' => $media_result['media_id'],
                    'tagged_users' => array()
                );
            }
        }

        $features = array(
            'tweetypie_unmention_optimization_enabled' => true,
            'responsive_web_edit_tweet_api_enabled' => true,
            'graphql_is_translatable_rweb_tweet_is_translatable_enabled' => true,
            'view_counts_everywhere_api_enabled' => true,
            'longform_notetweets_consumption_enabled' => true,
            'responsive_web_twitter_article_tweet_consumption_enabled' => false,
            'tweet_awards_web_tipping_enabled' => false,
            'longform_notetweets_rich_text_read_enabled' => true,
            'longform_notetweets_inline_media_enabled' => true,
            'responsive_web_graphql_exclude_directive_enabled' => true,
            'verified_phone_label_enabled' => false,
            'freedom_of_speech_not_reach_fetch_enabled' => true,
            'standardized_nudges_misinfo' => true,
            'tweet_with_visibility_results_prefer_gql_limited_actions_policy_enabled' => true,
            'responsive_web_media_download_video_enabled' => false,
            'responsive_web_graphql_skip_user_profile_image_extensions_enabled' => false,
            'responsive_web_graphql_timeline_navigation_enabled' => true,
            'responsive_web_enhance_cards_enabled' => false
        );

        $postData = json_encode(array(
            'variables' => $variables,
            'features' => $features,
            'queryId' => 'SoVnbfCycZ7fERGCwpZkYA'
        ));

        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'cookies' => $cookies,
            'body' => $postData,
            'timeout' => 30,
            'sslverify' => false
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'http_code' => wp_remote_retrieve_response_code($response),
                'headers' => wp_remote_retrieve_headers($response)
            );
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        $result = array(
            'success' => $httpCode === 200,
            'response' => json_decode($body, true),
            'http_code' => $httpCode,
            'headers' => $headers,
            'raw_response' => $body
        );

        if ($this->debug_mode) {
            error_log('TurkNews Twitter Auto - API Yanıtı: ' . print_r($result, true));
        }

        return $result;
    }

    private function upload_media($image_url) {
        // Görseli indir
        $image_data = wp_remote_get($image_url, array('timeout' => 30));
        
        if (is_wp_error($image_data)) {
            return array('success' => false, 'error' => 'Görsel indirilemedi');
        }
        
        $image_content = wp_remote_retrieve_body($image_data);
        
        // Geçici dosya oluştur
        $temp_file = wp_tempnam('twitter_media_');
        file_put_contents($temp_file, $image_content);

        // Medya yükleme URL'si
        $url = 'https://upload.twitter.com/1.1/media/upload.json';

        $headers = array(
            'Authorization' => 'Bearer AAAAAAAAAAAAAAAAAAAAANRILgAAAAAAnNwIzUejRCOuH5E6I8xnZz4puTs%3D1Zv7ttfk8LF81IUq16cHjhLTvJu4FA33AGWWjCpTnA',
            'x-csrf-token' => $this->csrf_token,
            'x-twitter-auth-type' => 'OAuth2Session',
            'x-twitter-active-user' => 'yes',
            'x-twitter-client-language' => 'tr',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );

        $cookies = array(
            'auth_token' => $this->auth_token,
            'ct0' => $this->csrf_token
        );

        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'cookies' => $cookies,
            'timeout' => 30,
            'sslverify' => false,
            'body' => array(
                'media' => new CURLFile($temp_file)
            )
        );

        $response = wp_remote_post($url, $args);

        // Geçici dosyayı sil
        unlink($temp_file);

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return array(
            'success' => $httpCode === 200,
            'media_id' => $result['media_id_string'] ?? '',
            'response' => $result
        );
    }

    public function get_user_tweets($username, $since_id = null) {
        $url = 'https://twitter.com/i/api/graphql/UserTweets/UserTweets';
        
        $variables = array(
            'userId' => $this->get_user_id($username),
            'count' => 20,
            'includePromotedContent' => false,
            'withQuickPromoteEligibilityTweetFields' => false,
            'withVoice' => true,
            'withV2Timeline' => true
        );
        
        if ($since_id) {
            $variables['since_id'] = $since_id;
        }
        
        $params = array(
            'variables' => json_encode($variables),
            'features' => json_encode(array(
                'responsive_web_graphql_exclude_directive_enabled' => true,
                'verified_phone_label_enabled' => false,
                'creator_subscriptions_tweet_preview_api_enabled' => true,
                'responsive_web_graphql_timeline_navigation_enabled' => true,
                'responsive_web_graphql_skip_user_profile_image_extensions_enabled' => false,
                'tweetypie_unmention_optimization_enabled' => true,
                'responsive_web_edit_tweet_api_enabled' => true,
                'graphql_is_translatable_rweb_tweet_is_translatable_enabled' => true,
                'view_counts_everywhere_api_enabled' => true,
                'longform_notetweets_consumption_enabled' => true,
                'responsive_web_twitter_article_tweet_consumption_enabled' => false,
                'tweet_awards_web_tipping_enabled' => false,
                'freedom_of_speech_not_reach_fetch_enabled' => true,
                'standardized_nudges_misinfo' => true,
                'tweet_with_visibility_results_prefer_gql_limited_actions_policy_enabled' => true,
                'longform_notetweets_rich_text_read_enabled' => true,
                'longform_notetweets_inline_media_enabled' => true,
                'responsive_web_media_download_video_enabled' => false,
                'responsive_web_enhance_cards_enabled' => false
            ))
        );
        
        $headers = array(
            'authorization' => 'Bearer ' . $this->auth_token,
            'x-csrf-token' => $this->csrf_token,
            'x-twitter-active-user' => 'yes',
            'x-twitter-auth-type' => 'OAuth2Session',
            'x-twitter-client-language' => 'tr'
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']['user']['result']['timeline']['timeline']['instructions'][0]['entries'])) {
            return false;
        }
        
        $tweets = array();
        $entries = $body['data']['user']['result']['timeline']['timeline']['instructions'][0]['entries'];
        
        foreach ($entries as $entry) {
            if (!isset($entry['content']['itemContent']['tweet_results']['result'])) {
                continue;
            }
            
            $tweet_data = $entry['content']['itemContent']['tweet_results']['result'];
            
            $tweet = array(
                'id' => $tweet_data['rest_id'],
                'text' => $tweet_data['legacy']['full_text'],
                'created_at' => $tweet_data['legacy']['created_at']
            );
            
            // Medya varsa ekle
            if (isset($tweet_data['legacy']['entities']['media'])) {
                $tweet['media'] = array();
                foreach ($tweet_data['legacy']['entities']['media'] as $media) {
                    if ($media['type'] === 'photo') {
                        $tweet['media'][] = $media['media_url_https'];
                    } elseif ($media['type'] === 'video') {
                        $tweet['media'][] = $media['video_info']['variants'][0]['url'];
                    }
                }
            }
            
            $tweets[] = $tweet;
        }
        
        return $tweets;
    }
    
    private function get_user_id($username) {
        $url = 'https://twitter.com/i/api/graphql/G3KGOASz96M-Qu0nwmYqNw/UserByScreenName';
        
        $variables = array(
            'screen_name' => $username,
            'withSafetyModeUserFields' => true
        );
        
        $params = array(
            'variables' => json_encode($variables)
        );
        
        $headers = array(
            'authorization' => 'Bearer ' . $this->auth_token,
            'x-csrf-token' => $this->csrf_token,
            'x-twitter-active-user' => 'yes',
            'x-twitter-auth-type' => 'OAuth2Session',
            'x-twitter-client-language' => 'tr'
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data']['user']['result']['rest_id'])) {
            return false;
        }
        
        return $body['data']['user']['result']['rest_id'];
    }
} 