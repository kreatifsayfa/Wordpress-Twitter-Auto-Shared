<?php
class Content_Optimizer {
    private $debug_mode;

    public function __construct() {
        $options = get_option('turknews_twitter_settings');
        $this->debug_mode = $options['debug_mode'] === 'yes';
    }

    public function optimize_content($post) {
        $options = get_option('turknews_twitter_settings');
        
        // Tweet metnini hazırla
        $tweet_text = $this->prepare_tweet_text($post, $options['tweet_template']);
        
        // Görsel URL'sini al
        $image_url = '';
        if ($options['include_image'] === 'yes') {
            $image_url = $this->get_post_image($post);
        }
        
        // Akıllı özellikleri uygula
        if ($options['smart_features'] === 'yes') {
            $smart_features = new Smart_Features();
            $tweet_text = $smart_features->optimize_tweet($tweet_text, $post);
        }
        
        return array(
            'tweet_text' => $tweet_text,
            'image_url' => $image_url
        );
    }

    private function prepare_tweet_text($post, $template) {
        // Şablon değişkenlerini değiştir
        $text = str_replace('{title}', $post->post_title, $template);
        $text = str_replace('{link}', get_permalink($post->ID), $text);
        
        // Kategori değişkenini değiştir
        $categories = get_the_category($post->ID);
        $category_name = !empty($categories) ? $categories[0]->name : '';
        $text = str_replace('{category}', $category_name, $text);
        
        return $text;
    }

    private function get_post_image($post) {
        // Öne çıkarılmış görseli kontrol et
        if (has_post_thumbnail($post->ID)) {
            return get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // İçerikteki ilk görseli bul
        $content = $post->post_content;
        if (preg_match('/<img.+?src=[\'"](.+?)[\'"].*?>/i', $content, $matches)) {
            return $matches[1];
        }
        
        return '';
    }

    private function optimize_title($post_id) {
        $title = get_the_title($post_id);
        
        // Başlık uzunluğunu kontrol et
        if (strlen($title) > 100) {
            $title = substr($title, 0, 97) . '...';
        }
        
        // Başlıkta özel karakterleri temizle
        $title = $this->clean_special_chars($title);
        
        return $title;
    }

    private function generate_hashtags($post_id) {
        $hashtags = array();
        
        // Kategori hashtag'leri
        $categories = get_the_category($post_id);
        foreach ($categories as $category) {
            $hashtags[] = '#' . str_replace(' ', '', $category->name);
        }
        
        // Anahtar kelime hashtag'leri
        $keywords = $this->extract_keywords($post_id);
        foreach ($keywords as $keyword) {
            if (count($hashtags) < 5) { // Maksimum 5 hashtag
                $hashtag = '#' . str_replace(' ', '', $keyword);
                if (!in_array($hashtag, $hashtags)) {
                    $hashtags[] = $hashtag;
                }
            }
        }
        
        return $hashtags;
    }

    private function select_template($post_id) {
        $category = get_the_category($post_id);
        $category_name = !empty($category) ? strtolower($category[0]->name) : '';
        
        $templates = array(
            'haber' => '📰 {title} {link}',
            'spor' => '⚽ {title} {link}',
            'ekonomi' => '💰 {title} {link}',
            'teknoloji' => '💻 {title} {link}',
            'sağlık' => '🏥 {title} {link}',
            'eğitim' => '📚 {title} {link}',
            'siyaset' => '🏛️ {title} {link}',
            'kültür' => '🎭 {title} {link}',
            'magazin' => '🌟 {title} {link}',
            'dünya' => '🌍 {title} {link}'
        );
        
        return isset($templates[$category_name]) ? $templates[$category_name] : '🔥 {title} {link}';
    }

    private function extract_keywords($post_id) {
        $content = get_post_field('post_content', $post_id);
        $title = get_the_title($post_id);
        
        // İçerikten stop kelimeleri temizle
        $content = $this->remove_stop_words($content);
        
        // Kelime frekanslarını hesapla
        $words = str_word_count(strtolower($content), 1);
        $word_counts = array_count_values($words);
        
        // En sık kullanılan kelimeleri al
        arsort($word_counts);
        $keywords = array_slice(array_keys($word_counts), 0, 5);
        
        // Başlıktaki kelimeleri ekle
        $title_words = str_word_count(strtolower($title), 1);
        $keywords = array_merge($keywords, $title_words);
        
        // Tekrarları kaldır
        $keywords = array_unique($keywords);
        
        return $keywords;
    }

    private function clean_special_chars($text) {
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        return trim($text);
    }

    private function remove_stop_words($text) {
        $stop_words = array(
            've', 'veya', 'ile', 'için', 'gibi', 'ama', 'fakat', 'ancak', 'çünkü',
            'eğer', 'ise', 'ki', 'de', 'da', 'den', 'dan', 'nin', 'nın', 'ler', 'lar'
        );
        
        $words = explode(' ', $text);
        $filtered_words = array_diff($words, $stop_words);
        
        return implode(' ', $filtered_words);
    }

    public function predict_engagement($post_id) {
        // Etkileşim tahmini için basit bir algoritma
        $factors = array(
            'title_length' => strlen(get_the_title($post_id)),
            'hashtag_count' => count($this->generate_hashtags($post_id)),
            'category' => get_the_category($post_id)[0]->name,
            'has_image' => has_post_thumbnail($post_id)
        );
        
        $score = 0;
        
        // Başlık uzunluğu puanı
        if ($factors['title_length'] > 50 && $factors['title_length'] < 100) {
            $score += 20;
        }
        
        // Hashtag sayısı puanı
        if ($factors['hashtag_count'] >= 2 && $factors['hashtag_count'] <= 5) {
            $score += 15;
        }
        
        // Kategori puanı
        $popular_categories = array('haber', 'spor', 'magazin');
        if (in_array(strtolower($factors['category']), $popular_categories)) {
            $score += 25;
        }
        
        // Görsel puanı
        if ($factors['has_image']) {
            $score += 40;
        }
        
        return $score;
    }
} 