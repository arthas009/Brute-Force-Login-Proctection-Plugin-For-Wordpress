<?PHP
/*
Plugin Name: Brute-Force Protection
Plugin URI: https://yok.com/
Description: A Brute-Force protection plugin for login page brute force attacks.
Version: 1.0.0
Author: Elendil/Arthas
Author URI: https://yok.com/
License: GNU
Text Domain: brute_force_protection
*/

$DENEME_KEY = "bfp_deneme_sayisi";
$DENEME_SAYISI = 10;

$DENEME_ARALIK_KEY = "bfp_deneme_araligi";
$DENEME_ARALIK = 10;

// Plugin'i direkt çağırmaya çalıştığında çalışmasın!
// Plugin won't work when directly called
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

register_activation_hook( __FILE__, 'aktivasyon' );     // Plugin aktif olunca çalışacak fonksiyon
// Function works when plugin activated

// Bu fonksiyon ile plugin'in kullanacağı karaliste tablosu veritabanında oluşturuluyor.
// This function builds a blacklist on database
function aktivasyon() {
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    global $wpdb;                                           // Wordpress'in veritabanı işleri için gerekli olan sınıfı tutan değişkeni alıyoruz.
    // Database variable for wordpress database
    $tablo_adi_karaliste = $wpdb->prefix ."bfp_karaliste";
    $tablo_adi_denemeler = $wpdb->prefix ."bfp_denemeler";
    $charset_collate = $wpdb->get_charset_collate();        // Karakter setini ayarlıyoruz.
    
    $tablo_sql_karaliste = "CREATE TABLE $tablo_adi_karaliste (
        id int(11) NOT NULL AUTO_INCREMENT,
        ip varchar(15) NOT NULL,
        banlayan_id int(11) NOT NULL DEFAULT 0,
        time datetime(0) NOT NULL DEFAULT CURRENT_TIMESTAMP(0),
        PRIMARY KEY  (id)
    ) $charset_collate;";                                   // Tablo için SQL komutu
    
    $tablo_sql_denemeler = "CREATE TABLE $tablo_adi_denemeler (
        ip varchar(15) NOT NULL,
        deneme_sayisi int(11) NOT NULL DEFAULT 1,
        zaman_siniri datetime(0) NOT NULL,
        PRIMARY KEY  (ip)
    ) $charset_collate;";                                   // Tablo için SQL komutu
    
    dbDelta($tablo_sql_karaliste);                                    // Komutu çalıştırıyoruz. Executing commands.
    dbDelta($tablo_sql_denemeler);                                    // Komutu çalıştırıyoruz. Executing commands.
}

add_action("admin_menu", "admin_menu_func");
function admin_menu_func() {
    add_menu_page('Brute-Force Protection','Brute-Force Protection', 
    'manage_options', 'brute_force_protection', 'yonetim_paneli_func'); // Sol menüye panel erişim linki ekleyelim.
}

function yonetim_paneli_func() {
    global $wpdb;
    global $DENEME_KEY;
    global $DENEME_SAYISI;
    global $DENEME_ARALIK_KEY;
    global $DENEME_ARALIK;
    
    if(wp_verify_nonce($_POST['brute_force_update'], 'brute_force_deger')) {
        if(isset($_POST["deneme_sayisi"]) && isset($_POST["deneme_aralik"]) && isset($_POST["engel_kaldir"])) {
            $deneme_sayisi = sanitize_text_field($_POST["deneme_sayisi"]);
            $deneme_aralik = sanitize_text_field($_POST["deneme_aralik"]);
            $engel_kaldir = sanitize_text_field($_POST["engel_kaldir"]);
            
            update_option($DENEME_KEY, $deneme_sayisi);
            update_option($DENEME_ARALIK_KEY, $deneme_aralik);
            
            $sonuc = 1;
            if($engel_kaldir != "IP") {
                $sonuc = $wpdb->query("DELETE FROM {$wpdb->prefix}bfp_karaliste WHERE ip = '$engel_kaldir'");
            }
            
            if($sonuc)
                echo "<center>Değişiklikler Başarıyla Kaydedildi</center>";
        }
    }
    
    ?>
        <center>
        <br>
        <br>
        <form action="" method="POST">
        <?PHP wp_nonce_field('brute_force_deger','brute_force_update'); ?>
        <table>
            <tr>
                <td>Deneme Sayısı:</td>
                <td><input type="text" maxlength="3" name="deneme_sayisi" value="<?PHP echo get_option($DENEME_KEY, $DENEME_SAYISI); ?>"></td>
            </tr><tr>
                <td>Deneme Aralığı(sn):</td>
                <td><input type="text" maxlength="3" name="deneme_aralik" value="<?PHP echo get_option($DENEME_ARALIK_KEY, $DENEME_ARALIK); ?>"></td>
            </tr><tr>
                <td>Engel Kaldır:</td>
                <td>
                <select name="engel_kaldir">
                <option value="IP">IP Adresi Seçin</option>
                <?PHP
                    $engelli_ipler = $wpdb->get_results("SELECT ip FROM {$wpdb->prefix}bfp_karaliste", ARRAY_A);
                    foreach($engelli_ipler as $ip) {
                        echo "<option value=\"{$ip["ip"]}\">{$ip["ip"]}</option>";
                    }
                ?>
                </select>
                </td>
            </tr><tr>
                <td colspan="2"><input type="submit" value="Kaydet"></td>
            </tr>
        </table>
        </form>
        </center>
    <?PHP
}

add_action("wp_login", "sifirla");
add_action("wp_authenticate", "isle");
add_action("login_head", "engelle");

function isle() {
    global $wpdb;
    global $DENEME_SAYISI;
    global $DENEME_ARALIK;
    global $DENEME_KEY;
    global $DENEME_ARALIK_KEY;
    date_default_timezone_set(get_option("timezone_string"));
    
    $ip = GetIP();
    $suan = new DateTime("now");
    $zaman_siniri = new DateTime("now");
    $zaman_siniri->add(new DateInterval("PT". get_option($DENEME_ARALIK_KEY, $DENEME_ARALIK) ."S"));
    
    $sonuc = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bfp_denemeler WHERE ip = '$ip'", "ARRAY_A");
    $karaliste_sonuc = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bfp_karaliste WHERE ip = '$ip'", "ARRAY_A");
    if($sonuc && !$karaliste_sonuc) {
        $db_zaman_siniri = new DateTime($sonuc["zaman_siniri"]);
		// if time is passed, then reset the amount of attempts of the current client.
        if($db_zaman_siniri < $suan) {
            $sonuc2 = $wpdb->query("UPDATE {$wpdb->prefix}bfp_denemeler SET deneme_sayisi = 1, zaman_siniri = '{$zaman_siniri->format('Y-m-d H:i:s')}' WHERE ip = '$ip'");
        }
		// else, increase the number of attempts 1 in database
		else {
            $deneme_sayisi = intval($sonuc["deneme_sayisi"]) + 1;
            $wpdb->query("UPDATE {$wpdb->prefix}bfp_denemeler SET deneme_sayisi = $deneme_sayisi WHERE ip = '$ip'");
            // if number of attempts is greater than the thrashold we gave, then add the client's ip on blacklist table.
            if($deneme_sayisi > intval(get_option($DENEME_KEY, $DENEME_SAYISI))) {
                $wpdb->query("INSERT INTO {$wpdb->prefix}bfp_karaliste (ip) VALUES ('$ip')");
            }
        }
    } else {
        $sql_komut = "INSERT INTO {$wpdb->prefix}bfp_denemeler (ip, zaman_siniri) VALUES ('$ip', '{$zaman_siniri->format('Y-m-d H:i:s')}')";
        $wpdb->query($sql_komut);
    }
}
// block the ip of attacker who passed amount of login attempt that we give from admin panel.
function engelle() {
    global $wpdb;
    
    $ip = GetIP();
    
    $sonuc = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bfp_karaliste WHERE ip = '$ip'", "ARRAY_A");
    if($sonuc) {
        die("<center>Bu sayfaya girişiniz engellenmiştir!<br><br>IP Adresiniz: $ip</center>");
    }
}


// Make zero the time variable
function sifirla() {
    global $wpdb;
    $ip = GetIP();
    $wpdb->query("DELETE FROM {$wpdb->prefix}bfp_denemeler WHERE ip = '$ip'");
}
 // get client ip
function GetIP() {
    if(getenv("HTTP_CLIENT_IP")) {
        $ip = getenv("HTTP_CLIENT_IP");
    } elseif(getenv("HTTP_X_FORWARDED_FOR")) {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        if (strstr($ip, ',')) {
            $tmp = explode (',', $ip);
            $ip = trim($tmp[0]);
        }
    } else {
        $ip = getenv("REMOTE_ADDR");
    }
    return $ip;
}