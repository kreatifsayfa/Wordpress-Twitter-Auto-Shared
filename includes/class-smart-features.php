<?php
class Smart_Features {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function optimize_tweet($tweet_text, $post = null) {
        $options = get_option('turknews_twitter_settings');
        
        // Hashtag optimizasyonu
        if ($options['optimize_hashtags'] === 'yes') {
            $tweet_text = $this->optimize_hashtags($tweet_text, $post);
        }
        
        // Emoji optimizasyonu
        if ($options['optimize_emojis'] === 'yes') {
            $tweet_text = $this->optimize_emojis($tweet_text, $post);
        }
        
        // Otomatik çeviri
        if ($options['auto_translate'] === 'yes') {
            $tweet_text = $this->auto_translate($tweet_text);
        }
        
        return $tweet_text;
    }

    private function optimize_hashtags($tweet_text, $post) {
        // Kategori hashtag'leri ekle
        if ($post) {
            $categories = get_the_category($post->ID);
            foreach ($categories as $category) {
                $hashtag = '#' . str_replace(' ', '', $category->name);
                if (strpos($tweet_text, $hashtag) === false) {
                    $tweet_text .= ' ' . $hashtag;
                }
            }
        }
        
        // Popüler hashtag'leri ekle
        $popular_hashtags = array('#haber', '#gündem', '#sonhaber', '#türkiye');
        foreach ($popular_hashtags as $hashtag) {
            if (strpos($tweet_text, $hashtag) === false) {
                $tweet_text .= ' ' . $hashtag;
            }
        }
        
        return $tweet_text;
    }

    private function optimize_emojis($tweet_text, $post) {
        // Kategoriye göre emoji ekle
        if ($post) {
            $categories = get_the_category($post->ID);
            foreach ($categories as $category) {
                $emoji = $this->get_category_emoji($category->name);
                if ($emoji && strpos($tweet_text, $emoji) === false) {
                    $tweet_text = $emoji . ' ' . $tweet_text;
                }
            }
        }
        
        return $tweet_text;
    }

    private function get_category_emoji($category_name) {
        $emoji_map = array(
            'haber' => '📰',
            'spor' => '⚽',
            'ekonomi' => '💰',
            'teknoloji' => '💻',
            'sağlık' => '🏥',
            'eğitim' => '📚',
            'siyaset' => '🏛️',
            'kültür' => '🎭',
            'dünya' => '🌍'
        );
        
        $category_name = strtolower($category_name);
        return $emoji_map[$category_name] ?? '📢';
    }

    private function auto_translate($text) {
        // Basit bir çeviri örneği
        // Gerçek uygulamada bir çeviri API'si kullanılmalı
        $translations = array(
            'news' => 'haber',
            'sports' => 'spor',
            'economy' => 'ekonomi',
            'technology' => 'teknoloji',
            'health' => 'sağlık',
            'education' => 'eğitim',
            'politics' => 'siyaset',
            'culture' => 'kültür',
            'world' => 'dünya'
        );
        
        foreach ($translations as $en => $tr) {
            $text = str_ireplace($en, $tr, $text);
        }
        
        return $text;
    }

    public function translate_text($text, $target_lang = 'tr') {
        // Çeviri API'si kullanılabilir
        // Şimdilik orijinal metni döndür
        return $text;
    }

    public function get_best_time_to_post() {
        // En iyi paylaşım zamanını hesapla
        // Şimdilik sabit bir zaman döndür
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }
} 