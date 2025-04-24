<?php
/**
 * Plugin Name: TurkNews Twitter Auto
 * Plugin URI: https://turknews.co.uk
 * Description: WordPress yazılarını otomatik olarak Twitter'da paylaşır
 * Version: 1.0.0
 * Author: TurkNews
 * Author URI: https://turknews.co.uk
 * Text Domain: turknews-twitter-auto
 * Requires PHP: 7.4
 * Requires at least: 5.6
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// PHP sürüm kontrolü
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo 'TurkNews Twitter Auto eklentisi için PHP 7.4 veya üstü sürüm gereklidir. Mevcut PHP sürümünüz: ' . PHP_VERSION;
        echo '</p></div>';
    });
    return;
}

// Gerekli bağımlılıkları kontrol et
if (!function_exists('curl_init')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo 'TurkNews Twitter Auto eklentisi için cURL PHP eklentisi gereklidir.';
        echo '</p></div>';
    });
    return;
}

if (!function_exists('json_decode')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo 'TurkNews Twitter Auto eklentisi için JSON PHP eklentisi gereklidir.';
        echo '</p></div>';
    });
    return;
}

// Plugin sınıfını tanımla
class TurkNewsTwitterAuto {
    private static $instance = null;
    private $twitter_api;
    private $smart_features;
    private $content_optimizer;
    private $analytics;
    private $scheduler;
    private $multi_account;
    private $engagement;
    private $ab_testing;
    private $error_manager;
    private $tweet_retweeter;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Gerekli dosyaları yükle
        require_once plugin_dir_path(__FILE__) . 'includes/class-twitter-api.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-smart-features.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-content-optimizer.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-analytics.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-scheduler.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-multi-account.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-engagement.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-ab-testing.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-error-manager.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-tweet-retweeter.php';

        // Plugin aktivasyonunda çalışacak hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Plugin deaktivasyonunda çalışacak hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // WordPress hook'larını ekle
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Yazı yayınlandığında tweet at
        add_action('publish_post', array($this, 'auto_tweet_post'), 10, 2);

        // Sınıfları başlat
        $this->twitter_api = new Twitter_API();
        $this->content_optimizer = new Content_Optimizer();
        $this->analytics = new Analytics();
        $this->scheduler = new Scheduler();
        $this->multi_account = new Multi_Account();
        $this->engagement = new Engagement();
        $this->ab_testing = new AB_Testing();
        $this->error_manager = new Error_Manager();
        $this->tweet_retweeter = new Tweet_Retweeter();
        
        // Hata loglarını kontrol et
        $this->check_error_logs();
        
        add_action('admin_notices', array($this, 'display_error_notices'));
    }

    public function activate() {
        // Aktivasyon işlemleri
        if (!get_option('turknews_twitter_settings')) {
            add_option('turknews_twitter_settings', array(
                'auth_token' => '',
                'csrf_token' => '',
                'auto_tweet' => 'yes',
                'tweet_template' => '🔥 {title} {link} #haber',
                'include_image' => 'yes',
                'debug_mode' => 'no',
                'smart_features' => 'yes',
                'auto_translate' => 'no',
                'optimize_hashtags' => 'yes',
                'optimize_emojis' => 'yes',
                'content_optimization' => 'yes',
                'engagement_prediction' => 'yes',
                'scheduled_tweets' => 'no'
            ));
        }

        // Twitter hesapları için varsayılan yapı
        if (!get_option('turknews_twitter_accounts')) {
            add_option('turknews_twitter_accounts', array());
        }

        // Twitter hesap istatistikleri için varsayılan yapı
        if (!get_option('turknews_twitter_account_stats')) {
            add_option('turknews_twitter_account_stats', array());
        }

        // Hedef profiller için varsayılan yapı
        if (!get_option('turknews_twitter_target_profiles')) {
            add_option('turknews_twitter_target_profiles', array());
        }

        // Paylaşım ayarları için varsayılan yapı
        if (!get_option('turknews_twitter_share_settings')) {
            add_option('turknews_twitter_share_settings', array(
                'auto_share' => 'no',
                'check_interval' => 60,
                'include_media' => 'yes',
                'max_tweets_per_day' => 10,
                'add_source' => 'yes'
            ));
        }

        // A/B testleri için varsayılan yapı
        if (!get_option('turknews_twitter_ab_tests')) {
            add_option('turknews_twitter_ab_tests', array());
        }

        // Etkileşim verileri için varsayılan yapı
        if (!get_option('turknews_twitter_engagement_data')) {
            add_option('turknews_twitter_engagement_data', array());
        }

        // Hata logları için varsayılan yapı
        if (!get_option('turknews_twitter_error_logs')) {
            add_option('turknews_twitter_error_logs', array());
        }

        // Zamanlanmış görevleri temizle
        wp_clear_scheduled_hook('turknews_twitter_check_target_profiles');
    }

    public function deactivate() {
        // Deaktivasyon işlemleri
    }

    public function init() {
        // Hedef profilleri kontrol et
        if ($this->tweet_retweeter->get_retweet_settings()['auto_share'] === 'yes') {
            add_action('turknews_twitter_check_target_profiles', array($this->tweet_retweeter, 'check_target_profiles'));
            
            if (!wp_next_scheduled('turknews_twitter_check_target_profiles')) {
                wp_schedule_event(time(), 'hourly', 'turknews_twitter_check_target_profiles');
            }
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'TurkNews Twitter Auto',
            'Twitter Auto',
            'manage_options',
            'turknews-twitter-auto',
            array($this, 'admin_page'),
            'dashicons-twitter',
            30
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'Raporlar',
            'Raporlar',
            'manage_options',
            'turknews-twitter-reports',
            array($this, 'reports_page')
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'Zamanlanmış Tweet\'ler',
            'Zamanlanmış Tweet\'ler',
            'manage_options',
            'turknews-twitter-scheduled',
            array($this, 'scheduled_tweets_page')
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'Twitter Hesapları',
            'Twitter Hesapları',
            'manage_options',
            'turknews-twitter-accounts',
            array($this, 'accounts_page')
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'Etkileşim Raporları',
            'Etkileşim Raporları',
            'manage_options',
            'turknews-twitter-engagement',
            array($this, 'engagement_page')
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'A/B Testleri',
            'A/B Testleri',
            'manage_options',
            'turknews-twitter-ab-tests',
            array($this, 'ab_tests_page')
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'Hata Yönetimi',
            'Hata Yönetimi',
            'manage_options',
            'turknews-twitter-errors',
            array($this, 'error_management_page')
        );

        add_submenu_page(
            'turknews-twitter-auto',
            'Hedef Profiller',
            'Hedef Profiller',
            'manage_options',
            'turknews-twitter-target-profiles',
            array($this, 'target_profiles_page')
        );
    }

    public function register_settings() {
        register_setting('turknews_twitter_settings', 'turknews_twitter_settings');
    }

    public function admin_page() {
        // Ayarlar sayfası içeriği
        ?>
        <div class="wrap">
            <h1>TurkNews Twitter Ayarları</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('turknews_twitter_settings');
                $options = get_option('turknews_twitter_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Twitter Auth Token</th>
                        <td>
                            <input type="text" name="turknews_twitter_settings[auth_token]" 
                                   value="<?php echo esc_attr($options['auth_token']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Twitter CSRF Token</th>
                        <td>
                            <input type="text" name="turknews_twitter_settings[csrf_token]" 
                                   value="<?php echo esc_attr($options['csrf_token']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Otomatik Tweet</th>
                        <td>
                            <select name="turknews_twitter_settings[auto_tweet]">
                                <option value="yes" <?php selected($options['auto_tweet'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['auto_tweet'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tweet Şablonu</th>
                        <td>
                            <input type="text" name="turknews_twitter_settings[tweet_template]" 
                                   value="<?php echo esc_attr($options['tweet_template']); ?>" class="regular-text">
                            <p class="description">Kullanılabilir değişkenler: {title}, {link}, {category}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Görsel Ekle</th>
                        <td>
                            <select name="turknews_twitter_settings[include_image]">
                                <option value="yes" <?php selected($options['include_image'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['include_image'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Akıllı Özellikler</th>
                        <td>
                            <select name="turknews_twitter_settings[smart_features]">
                                <option value="yes" <?php selected($options['smart_features'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['smart_features'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Otomatik Çeviri</th>
                        <td>
                            <select name="turknews_twitter_settings[auto_translate]">
                                <option value="yes" <?php selected($options['auto_translate'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['auto_translate'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hashtag Optimizasyonu</th>
                        <td>
                            <select name="turknews_twitter_settings[optimize_hashtags]">
                                <option value="yes" <?php selected($options['optimize_hashtags'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['optimize_hashtags'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Emoji Optimizasyonu</th>
                        <td>
                            <select name="turknews_twitter_settings[optimize_emojis]">
                                <option value="yes" <?php selected($options['optimize_emojis'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['optimize_emojis'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">İçerik Optimizasyonu</th>
                        <td>
                            <select name="turknews_twitter_settings[content_optimization]">
                                <option value="yes" <?php selected($options['content_optimization'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['content_optimization'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Etkileşim Tahmini</th>
                        <td>
                            <select name="turknews_twitter_settings[engagement_prediction]">
                                <option value="yes" <?php selected($options['engagement_prediction'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['engagement_prediction'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Debug Modu</th>
                        <td>
                            <select name="turknews_twitter_settings[debug_mode]">
                                <option value="yes" <?php selected($options['debug_mode'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['debug_mode'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Zamanlanmış Tweet</th>
                        <td>
                            <select name="turknews_twitter_settings[scheduled_tweets]">
                                <option value="yes" <?php selected($options['scheduled_tweets'], 'yes'); ?>>Evet</option>
                                <option value="no" <?php selected($options['scheduled_tweets'], 'no'); ?>>Hayır</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function reports_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>Twitter Raporları</h1>';
        echo $this->analytics->generate_html_report();
        echo '</div>';
    }

    public function scheduled_tweets_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $scheduled_tweets = $this->scheduler->get_scheduled_tweets();
        
        echo '<div class="wrap">';
        echo '<h1>Zamanlanmış Tweet\'ler</h1>';
        
        if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['post_id'])) {
            $this->scheduler->cancel_scheduled_tweet($_GET['post_id']);
            echo '<div class="notice notice-success"><p>Tweet zamanlaması iptal edildi.</p></div>';
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Yazı ID</th><th>Başlık</th><th>Zamanlanan Tarih</th><th>Durum</th><th>İşlemler</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($scheduled_tweets as $post_id => $tweet_data) {
            $post = get_post($post_id);
            $status_label = array(
                'pending' => 'Bekliyor',
                'published' => 'Yayınlandı',
                'failed' => 'Başarısız'
            );
            
            echo '<tr>';
            echo '<td>' . $post_id . '</td>';
            echo '<td>' . ($post ? $post->post_title : 'Yazı bulunamadı') . '</td>';
            echo '<td>' . $tweet_data['scheduled_time'] . '</td>';
            echo '<td>' . $status_label[$tweet_data['status']] . '</td>';
            echo '<td>';
            if ($tweet_data['status'] === 'pending') {
                echo '<a href="' . admin_url('admin.php?page=turknews-twitter-scheduled&action=cancel&post_id=' . $post_id) . '" class="button">İptal Et</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }

    public function accounts_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $accounts = $this->multi_account->get_accounts();
        
        echo '<div class="wrap">';
        echo '<h1>Twitter Hesapları</h1>';
        
        if (isset($_POST['action'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'turknews_twitter_accounts')) {
                wp_die('Güvenlik doğrulaması başarısız oldu.');
            }

            if ($_POST['action'] === 'add_account') {
                $account_data = array(
                    'username' => sanitize_text_field($_POST['username']),
                    'auth_token' => sanitize_text_field($_POST['auth_token']),
                    'csrf_token' => sanitize_text_field($_POST['csrf_token'])
                );
                $this->multi_account->add_account($account_data);
                echo '<div class="notice notice-success"><p>Hesap başarıyla eklendi.</p></div>';
            } elseif ($_POST['action'] === 'delete_account' && isset($_POST['account_id'])) {
                $this->multi_account->delete_account($_POST['account_id']);
                echo '<div class="notice notice-success"><p>Hesap başarıyla silindi.</p></div>';
            }
        }
        
        // Yeni hesap ekleme formu
        echo '<h2>Yeni Hesap Ekle</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field('turknews_twitter_accounts');
        echo '<input type="hidden" name="action" value="add_account">';
        echo '<table class="form-table">';
        echo '<tr><th>Kullanıcı Adı</th><td><input type="text" name="username" required></td></tr>';
        echo '<tr><th>Auth Token</th><td><input type="text" name="auth_token" required></td></tr>';
        echo '<tr><th>CSRF Token</th><td><input type="text" name="csrf_token" required></td></tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" class="button button-primary" value="Hesap Ekle"></p>';
        echo '</form>';
        
        // Mevcut hesaplar
        echo '<h2>Mevcut Hesaplar</h2>';
        
        if (empty($accounts)) {
            echo '<div class="notice notice-info"><p>Henüz Twitter hesabı eklenmemiş.</p></div>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Kullanıcı Adı</th><th>Durum</th><th>Son Tweet</th><th>Toplam Tweet</th><th>İşlemler</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($accounts as $account_id => $account) {
                $stats = $this->multi_account->get_account_stats($account_id);
                
                echo '<tr>';
                echo '<td>' . esc_html($account['username']) . '</td>';
                echo '<td>' . ($account['is_active'] ? 'Aktif' : 'Pasif') . '</td>';
                echo '<td>' . ($stats['last_tweet_time'] ? $stats['last_tweet_time'] : 'Henüz tweet yok') . '</td>';
                echo '<td>' . $stats['total_tweets'] . '</td>';
                echo '<td>';
                echo '<form method="post" action="" style="display:inline;">';
                echo '<input type="hidden" name="action" value="delete_account">';
                echo '<input type="hidden" name="account_id" value="' . esc_attr($account_id) . '">';
                echo '<input type="submit" class="button" value="Sil" onclick="return confirm(\'Bu hesabı silmek istediğinizden emin misiniz?\')">';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    public function engagement_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $accounts = $this->multi_account->get_accounts();
        
        echo '<div class="wrap">';
        echo '<h1>Etkileşim Raporları</h1>';
        
        if (empty($accounts)) {
            echo '<div class="notice notice-warning"><p>Henüz Twitter hesabı eklenmemiş.</p></div>';
            return;
        }
        
        // Hesap seçimi
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="turknews-twitter-engagement">';
        echo '<select name="account_id">';
        foreach ($accounts as $account_id => $account) {
            echo '<option value="' . esc_attr($account_id) . '" ' . 
                 selected(isset($_GET['account_id']) ? $_GET['account_id'] : '', $account_id, false) . '>' . 
                 esc_html($account['username']) . '</option>';
        }
        echo '</select>';
        echo '<input type="submit" class="button" value="Raporu Göster">';
        echo '</form>';
        
        // Seçili hesap için raporu göster
        if (isset($_GET['account_id']) && isset($accounts[$_GET['account_id']])) {
            echo $this->engagement->generate_engagement_report($_GET['account_id']);
        }
        
        echo '</div>';
    }

    public function ab_tests_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>A/B Testleri</h1>';
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create_test' && isset($_POST['post_id'])) {
                $post_id = intval($_POST['post_id']);
                $variations = array();
                
                foreach ($_POST['variations'] as $variation) {
                    if (!empty($variation['text'])) {
                        $variations[] = array(
                            'text' => sanitize_text_field($variation['text']),
                            'image_url' => sanitize_text_field($variation['image_url'])
                        );
                    }
                }
                
                if (!empty($variations)) {
                    $test_id = $this->ab_testing->create_test($post_id, $variations);
                    echo '<div class="notice notice-success"><p>A/B testi başarıyla oluşturuldu.</p></div>';
                }
            } elseif ($_POST['action'] === 'end_test' && isset($_POST['test_id'])) {
                $this->ab_testing->end_test($_POST['test_id']);
                echo '<div class="notice notice-success"><p>A/B testi tamamlandı.</p></div>';
            }
        }
        
        // Yeni test oluşturma formu
        echo '<h2>Yeni A/B Testi Oluştur</h2>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="create_test">';
        echo '<table class="form-table">';
        echo '<tr><th>Yazı ID</th><td><input type="number" name="post_id" required></td></tr>';
        
        // Varyasyonlar
        echo '<tr><th>Varyasyonlar</th><td>';
        echo '<div id="variations">';
        echo '<div class="variation">';
        echo '<input type="text" name="variations[0][text]" placeholder="Tweet metni" required>';
        echo '<input type="text" name="variations[0][image_url]" placeholder="Görsel URL">';
        echo '</div>';
        echo '</div>';
        echo '<button type="button" class="button" onclick="addVariation()">Varyasyon Ekle</button>';
        echo '</td></tr>';
        
        echo '</table>';
        echo '<p class="submit"><input type="submit" class="button button-primary" value="Testi Başlat"></p>';
        echo '</form>';
        
        // Aktif testler
        $active_tests = $this->ab_testing->get_active_tests();
        if (!empty($active_tests)) {
            echo '<h2>Aktif Testler</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Test ID</th><th>Yazı ID</th><th>Oluşturulma Tarihi</th><th>İşlemler</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($active_tests as $test_id => $test) {
                echo '<tr>';
                echo '<td>' . $test_id . '</td>';
                echo '<td>' . $test['post_id'] . '</td>';
                echo '<td>' . $test['created_at'] . '</td>';
                echo '<td>';
                echo '<form method="post" action="" style="display:inline;">';
                echo '<input type="hidden" name="action" value="end_test">';
                echo '<input type="hidden" name="test_id" value="' . esc_attr($test_id) . '">';
                echo '<input type="submit" class="button" value="Testi Bitir">';
                echo '</form>';
                echo ' <a href="' . admin_url('admin.php?page=turknews-twitter-ab-tests&view=report&test_id=' . $test_id) . '" class="button">Raporu Gör</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Tamamlanan testler
        $completed_tests = $this->ab_testing->get_completed_tests();
        if (!empty($completed_tests)) {
            echo '<h2>Tamamlanan Testler</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Test ID</th><th>Yazı ID</th><th>En İyi Varyasyon</th><th>Tamamlanma Tarihi</th><th>İşlemler</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($completed_tests as $test_id => $test) {
                echo '<tr>';
                echo '<td>' . $test_id . '</td>';
                echo '<td>' . $test['post_id'] . '</td>';
                echo '<td>' . $test['best_variation'] . '</td>';
                echo '<td>' . $test['completed_at'] . '</td>';
                echo '<td>';
                echo '<a href="' . admin_url('admin.php?page=turknews-twitter-ab-tests&view=report&test_id=' . $test_id) . '" class="button">Raporu Gör</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Test raporu görüntüleme
        if (isset($_GET['view']) && $_GET['view'] === 'report' && isset($_GET['test_id'])) {
            echo $this->ab_testing->generate_test_report($_GET['test_id']);
        }
        
        echo '</div>';
        
        // JavaScript
        echo '<script>
        function addVariation() {
            var variations = document.getElementById("variations");
            var count = variations.getElementsByClassName("variation").length;
            var newVariation = document.createElement("div");
            newVariation.className = "variation";
            newVariation.innerHTML = \'<input type="text" name="variations[\' + count + \'][text]" placeholder="Tweet metni" required> <input type="text" name="variations[\' + count + \'][image_url]" placeholder="Görsel URL">\';
            variations.appendChild(newVariation);
        }
        </script>';
    }

    public function error_management_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Hata raporunu oluştur
        $error_report = $this->error_manager->generate_error_report();

        echo '<div class="wrap">';
        echo '<h1>Hata Yönetimi</h1>';
        echo $error_report;
        echo '</div>';
    }

    public function target_profiles_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $target_profiles = $this->tweet_retweeter->get_target_profiles();
        $retweet_settings = $this->tweet_retweeter->get_retweet_settings();
        
        echo '<div class="wrap">';
        echo '<h1>Hedef Profiller</h1>';
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add_profile') {
                $profile_data = array(
                    'username' => sanitize_text_field($_POST['username'])
                );
                $this->tweet_retweeter->add_target_profile($profile_data);
                echo '<div class="notice notice-success"><p>Profil başarıyla eklendi.</p></div>';
            } elseif ($_POST['action'] === 'update_settings') {
                $settings = array(
                    'auto_share' => sanitize_text_field($_POST['auto_share']),
                    'check_interval' => intval($_POST['check_interval']),
                    'include_media' => sanitize_text_field($_POST['include_media']),
                    'max_tweets_per_day' => intval($_POST['max_tweets_per_day']),
                    'add_source' => sanitize_text_field($_POST['add_source'])
                );
                $this->tweet_retweeter->update_retweet_settings($settings);
                echo '<div class="notice notice-success"><p>Ayarlar güncellendi.</p></div>';
            }
        }
        
        // Ayarlar formu
        echo '<h2>Paylaşım Ayarları</h2>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="update_settings">';
        echo '<table class="form-table">';
        echo '<tr><th>Otomatik Paylaşım</th><td>';
        echo '<select name="auto_share">';
        echo '<option value="yes" ' . selected($retweet_settings['auto_share'], 'yes', false) . '>Evet</option>';
        echo '<option value="no" ' . selected($retweet_settings['auto_share'], 'no', false) . '>Hayır</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Kontrol Aralığı (dakika)</th><td>';
        echo '<input type="number" name="check_interval" value="' . esc_attr($retweet_settings['check_interval']) . '" min="1">';
        echo '</td></tr>';
        echo '<tr><th>Medya Dahil Et</th><td>';
        echo '<select name="include_media">';
        echo '<option value="yes" ' . selected($retweet_settings['include_media'], 'yes', false) . '>Evet</option>';
        echo '<option value="no" ' . selected($retweet_settings['include_media'], 'no', false) . '>Hayır</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Kaynak Profili Ekle</th><td>';
        echo '<select name="add_source">';
        echo '<option value="yes" ' . selected($retweet_settings['add_source'], 'yes', false) . '>Evet</option>';
        echo '<option value="no" ' . selected($retweet_settings['add_source'], 'no', false) . '>Hayır</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Günlük Maksimum Tweet</th><td>';
        echo '<input type="number" name="max_tweets_per_day" value="' . esc_attr($retweet_settings['max_tweets_per_day']) . '" min="1">';
        echo '</td></tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" class="button button-primary" value="Ayarları Kaydet"></p>';
        echo '</form>';
        
        // Yeni profil ekleme formu
        echo '<h2>Yeni Hedef Profil Ekle</h2>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="add_profile">';
        echo '<table class="form-table">';
        echo '<tr><th>Twitter Kullanıcı Adı</th><td>';
        echo '<input type="text" name="username" required placeholder="@kullaniciadi">';
        echo '</td></tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" class="button button-primary" value="Profil Ekle"></p>';
        echo '</form>';
        
        // Mevcut profiller
        echo '<h2>Hedef Profiller</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Kullanıcı Adı</th><th>Son Kontrol</th><th>Son Tweet ID</th><th>Durum</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($target_profiles as $profile) {
            echo '<tr>';
            echo '<td>' . esc_html($profile['username']) . '</td>';
            echo '<td>' . ($profile['last_checked'] ? $profile['last_checked'] : 'Henüz kontrol edilmedi') . '</td>';
            echo '<td>' . ($profile['last_tweet_id'] ? $profile['last_tweet_id'] : '-') . '</td>';
            echo '<td>' . ($profile['is_active'] ? 'Aktif' : 'Pasif') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }

    public function auto_tweet_post($post_id, $post) {
        if (get_post_meta($post_id, '_tweeted', true)) {
            return;
        }

        $options = get_option('turknews_twitter_settings');
        if ($options['auto_tweet'] !== 'yes') {
            return;
        }

        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        // İçerik optimizasyonu
        $optimized_content = $this->content_optimizer->optimize_content($post);
        
        // A/B testi kontrolü
        $active_tests = $this->ab_testing->get_active_tests();
        foreach ($active_tests as $test_id => $test) {
            if ($test['post_id'] == $post_id) {
                // Rastgele bir varyasyon seç
                $variation = $test['variations'][array_rand($test['variations'])];
                
                // Tweet hazırlama
                $tweet_data = array(
                    'text' => $variation['text'],
                    'image_url' => $variation['image_url']
                );
                
                // Tweet'i gönder
                $results = $this->multi_account->send_tweet_to_accounts($tweet_data);
                
                // İstatistikleri kaydet
                foreach ($results as $account_id => $result) {
                    $this->multi_account->update_account_stats($account_id, $result);
                    $this->analytics->track_tweet($post_id, $tweet_data, $result);
                    
                    // Etkileşim verilerini takip et
                    if ($result['success'] && $result['tweet_id']) {
                        $engagement_data = $this->engagement->track_engagement($result['tweet_id'], $account_id);
                        if ($engagement_data) {
                            $this->ab_testing->update_test_results($test_id, $variation['text'], $engagement_data);
                        }
                    }
                }
                
                return;
            }
        }
        
        // Normal tweet gönderimi
        $tweet_data = array(
            'text' => $optimized_content['tweet_text'],
            'image_url' => $optimized_content['image_url']
        );

        // Zamanlanmış tweet kontrolü
        if ($options['scheduled_tweets'] === 'yes') {
            $scheduled_time = $this->scheduler->get_best_time_to_post($post_id);
            $this->scheduler->schedule_tweet($post_id, $scheduled_time);
            return;
        }

        // Tweet'i gönder
        $results = $this->multi_account->send_tweet_to_accounts($tweet_data);

        // İstatistikleri kaydet
        foreach ($results as $account_id => $result) {
            $this->multi_account->update_account_stats($account_id, $result);
            $this->analytics->track_tweet($post_id, $tweet_data, $result);
            
            // Etkileşim verilerini takip et
            if ($result['success'] && $result['tweet_id']) {
                $this->engagement->track_engagement($result['tweet_id'], $account_id);
            }
        }

        // Başarılı tweet'leri işaretle
        $success_count = count(array_filter($results, function($result) {
            return $result['success'];
        }));

        if ($success_count > 0) {
            update_post_meta($post_id, '_tweeted', true);
            update_post_meta($post_id, '_tweet_ids', array_map(function($result) {
                return $result['tweet_id'];
            }, $results));
        }
    }

    private function check_error_logs() {
        $error_logs = get_option('turknews_twitter_error_logs', array());
        if (!empty($error_logs)) {
            foreach ($error_logs as $error) {
                if ($error['status'] === 'pending') {
                    $this->error_manager->log_error(
                        $error['code'],
                        $error['message'],
                        $error['context']
                    );
                }
            }
        }
    }

    public function display_error_notices() {
        $error_logs = get_option('turknews_twitter_error_logs', array());
        if (!empty($error_logs)) {
            foreach ($error_logs as $error) {
                if ($error['status'] === 'pending') {
                    echo '<div class="notice notice-error">';
                    echo '<p><strong>Twitter Eklentisi Hatası:</strong> ' . esc_html($error['message']) . '</p>';
                    echo '<p>Hata Kodu: ' . esc_html($error['code']) . '</p>';
                    echo '</div>';
                }
            }
        }
    }
}

// Plugin'i başlat
TurkNewsTwitterAuto::get_instance(); 