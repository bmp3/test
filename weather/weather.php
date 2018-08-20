<?php
/*
Plugin Name: Wheather statistic Plugin
Description: The plugin shows weather statistic
Text Domain: weather
Domain Path: /languages/
Author: SecretLab
Version: 1.0.1
*/

class Weather_Informer {

    static $locale;

    static function init() {
        add_action('plugins_loaded', 'Weather_Informer::weather_plugin_init');
        add_shortcode( 'pogoda', 'Weather_Informer::get_weather_statistic' );
        $locale = get_locale();
        if ($locale == 'ru_RU') Weather_Informer::$locale = 'lang_ru';
        else Weather_Informer::$locale = 'en';
    }

    static function weather_plugin_init()
    {
        load_plugin_textdomain('weather', false, dirname(plugin_basename(__FILE__) ) . '/languages/');
    }

    static function get_usage_data($data)
    {
        $r = array();
        if (isset($data['mode']) && $data['mode'] == 'current') {
            $current = $data;
            $r['current']['tempC'] = $current['temp_C'];
            $r['current']['icon'] = $current['weatherIconUrl'][0]['value'];
            $r['current']['lang_ru'] = $current['lang_ru'][0]['value'];
            $r['current']['en'] = $current['weatherDesc'][0]['value'];
            $r['current']['cloudcover'] = $current['cloudcover'];
            $r['current']['time'] = date( 'H:i:s' ); //$current['observation_time'];
        } else {
            $r['date'] = $data['date'];
            $r['maxtc'] = $data['maxtempC'];
            $r['mintc'] = $data['mintempC'];
            $hourly = $data['hourly'];
            foreach ( $hourly as $i => $set ) {
                $r[$set['time']] = array(
                    'icon' => $hourly[$i]['weatherIconUrl'][0]['value'],
                    'cloudcover' => $hourly[$i]['cloudcover'],
                    'tempC' => $hourly[$i]['tempC'],
                    'lang_ru' => $hourly[$i]['lang_ru'][0]['value'],
                    'en' => $hourly[$i]['weatherDesc'][0]['value'],
                    'time' => $hourly[$i]['time']
                );
            }
            /*$r['day'] = array('icon' => $hourly[0]['weatherIconUrl'][0]['value'],
                'cloudcover' => $hourly[0]['cloudcover'],
                'tempC' => $hourly[0]['tempC'],
                'lang_ru' => $hourly[0]['lang_ru'][0]['value'],
                'en' => $hourly[0]['weatherDesc'][0]['value'],
                'time' => $hourly[0]['time']
            );
            $r['night'] = array('icon' => $hourly[1]['weatherIconUrl'][0]['value'],
                'cloudcover' => $hourly[1]['cloudcover'],
                'tempC' => $hourly[1]['tempC'],
                'lang_ru' => $hourly[1]['lang_ru'][0]['value'],
                'en' => $hourly[1]['weatherDesc'][0]['value'],
                'time' => $hourly[1]['time']
            );*/
        }

    return $r;

    }


    static function get_weather_statistic( $atts )
    {

        $result = array();

        $file = file_get_contents('https://api.worldweatheronline.com/premium/v1/weather.ashx?q=' . custom_sanitize_title( $atts['city'] ) . '&tp=3&date=' . date('Y-m-d') . '&showlocaltime&lang=ru&format=json&key=8e0ba9130d5b46aeb74151245181708');
        $info = json_decode($file, true);

        $info['data']['current_condition'][0]['mode'] = 'current';
        $result['today']['current'] = Weather_Informer::get_usage_data($info['data']['current_condition'][0]);
        $result['today']['statistic'] = Weather_Informer::get_usage_data($info['data']['weather'][0]);


        $file = file_get_contents('https://api.worldweatheronline.com/premium/v1/past-weather.ashx?q=' . custom_sanitize_title( $atts['city'] ) . '&tp=3&date=' . date('Y-m-d', strtotime('-11 days')) . '&enddate=' . date('Y-m-d', strtotime('-1 days')) . '&showlocaltime&lang=ru&format=json&key=8e0ba9130d5b46aeb74151245181708');
        $info = json_decode($file, true);

        $info['data']['weather'] = array_reverse($info['data']['weather']);
        foreach ($info['data']['weather'] as $i => $set) {
            $set['mode'] = 'past';
            $result['past'][$set['date']] = Weather_Informer::get_usage_data($set);
        }

        echo Weather_Informer::weather_statistic( $result, $atts['city'] );

    }


    static function get_single_weather_block($data)
    {

        if ( is_array( $data ) ) {
            $out =
                '<div class="time">' . str_replace('00', '.00', $data['time']) . '</div>
                 <div class="wt-icon"><img src="' . $data['icon'] . '"></div>
                 <div class="wt-content">
                     <div class="wt-temp h-row"><div class="title">' . __('Temperature', 'weather') . '</div><div class="content">' . $data['tempC'] . ' &#176;C</div></div>
                     <div class="wt-cloudcover h-row"><div class="title">' . __('Cloudcover', 'weather') . '</div><div class="content">' . $data['cloudcover'] . ' %</div></div>
                 </div>
                 <div class="wt-description">' . $data[Weather_Informer::$locale] . '</div>';

            return $out;
        }
        else return;

    }


    static function weather_statistic( $data, $city )
    {

        $out = '<div class="wt-box">';
        $out .=
            '<div class="today-box">
                <h1>' . __('Today Wheater', 'weather') . ' - ' . $city . '</h1>
                <h2>' . __('Current State', 'weather') . '</h2>
                <div class="current-info info-box">' .
                    Weather_Informer::get_single_weather_block($data['today']['current']['current']) .
                '</div>
                <h2>' . __('Today weather info and forecast', 'weather') . '</h2>
                <div class="today-statistic info-box">';

                foreach ( $data['today']['statistic'] as $i => $set ) {
                    if ( is_array( $set ) ) {
                        $out .=
                            '<div class="info-content day" > ' .
                                Weather_Informer::get_single_weather_block($set) .
                            '</div >';
                    }

                 }

            $out .=
                '</div>
            </div>';

        $out .= '<div class="wt-history opened"><h1 class="wt-opener">' . __('10 days statistic', 'weather') . '</h1>';

        $day_temp = $night_temp = array();
        $past_info = '';

        foreach ($data['past'] as $date => $data) {

            $day_temp[] = $data['1500']['tempC'];
            $night_temp[] = $data['300']['tempC'];

            $past_info .=
                '<div class="history-box info-box">
                 <div class="wt-date">' . $data['date'] . '</div>
                 <div class="info-content day">' .
                     Weather_Informer::get_single_weather_block($data['300']) .
                '</div>  
                 <div class="info-content night">' .
                     Weather_Informer::get_single_weather_block($data['1500']) .
                '</div>          
            </div>';
        }

        $out .=
            '<div class"average-temp">
                <div class="h-row">
                    <div class="title">' . __('Average day temperature', 'weather') . '</div>
                    <div class="content">' . round(array_sum($day_temp) / count($day_temp), 1) . ' &#176;C</div>
                </div>  
                <div class="h-row">
                    <div class="title">' . __('Average night temperature', 'weather') . '</div>
                    <div class="content">' . round(array_sum($night_temp) / count($night_temp), 1) . ' &#176;C</div>
                </div>     
            </div>';

        $out .= $past_info;

        $out .= '</div>';

        $out .= '</div>';

        $out .=
            '<style>
                .wt-box { }
                .today-box { }
                .today-box .current-info { }
                .today-box .today-statistic { display : flex; flex-wrap : wrap; }
                .today-statistic .info-content { flex-wrap : wrap; width : 24%; }
                .wt-box h1 { }
                .info-box { }
                .info-content .time { margin-bottom : 10px; }
                .wt-icon { display : inline-block; vertical-align : top; }
                .wt-icon img { }
                .wt-content { display : inline-block; }
                .wt-description { margin-top : 10px; font-style : italic; }
                .wp-temp { }
                .h-row { display : flex; }
                .h-row .title { }
                .h-row .content { padding : 0 15px; font-weight : 700; }
                .wt-history { }
                .wt-history h1 { }
                .average-temp { }
                .history-box {  }
                .history-box  .wt-date { margin : 15px 0 15px 0; font-size : 1.2rem; font-weight : 700; }
                .history-box .info-content { display : inline-block; }
                .wt-opener { position : relative; cursor : pointer; background-color : #c2c2c2; }
                .wt-opener::after { position : absolute; font-family : "dashicons"; content : "\f347"; font-size : 16px; position : absolute; right : 15px; top : 50%; transform : translateY( -50% ); }
                .wt-history .history-box { display : none; }
                .wt-history.opened .history-box { display : block; }
                .wt-history.opened .wt-opener::after { content : "\f343"; }
             </style>
             <script type="text/javascript">
                 jQuery(document).ready( function( $ ) {
                     $(".wt-opener").on( "click", function ( e ) {
                         $(".wt-history").toggleClass("opened");
                     });    
                 });
             </script>';

        return $out;

    }

}

Weather_Informer::init();



function custom_sanitize_title($title) {
    global $wpdb;

    $iso9_table = array(
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Ѓ' => 'G',
        'Ґ' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Є' => 'YE',
        'Ж' => 'ZH', 'З' => 'Z', 'Ѕ' => 'Z', 'И' => 'I', 'Й' => 'J',
        'Ј' => 'J', 'І' => 'I', 'Ї' => 'YI', 'К' => 'K', 'Ќ' => 'K',
        'Л' => 'L', 'Љ' => 'L', 'М' => 'M', 'Н' => 'N', 'Њ' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
        'У' => 'U', 'Ў' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'TS',
        'Ч' => 'CH', 'Џ' => 'DH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => '',
        'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ѓ' => 'g',
        'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'є' => 'ye',
        'ж' => 'zh', 'з' => 'z', 'ѕ' => 'z', 'и' => 'i', 'й' => 'j',
        'ј' => 'j', 'і' => 'i', 'ї' => 'yi', 'к' => 'k', 'ќ' => 'k',
        'л' => 'l', 'љ' => 'l', 'м' => 'm', 'н' => 'n', 'њ' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ў' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
        'ч' => 'ch', 'џ' => 'dh', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
    );
    $geo2lat = array(
        'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v',
        'ზ' => 'z', 'თ' => 'th', 'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm',
        'ნ' => 'n', 'ო' => 'o', 'პ' => 'p','ჟ' => 'zh','რ' => 'r','ს' => 's',
        'ტ' => 't','უ' => 'u','ფ' => 'ph','ქ' => 'q','ღ' => 'gh','ყ' => 'qh',
        'შ' => 'sh','ჩ' => 'ch','ც' => 'ts','ძ' => 'dz','წ' => 'ts','ჭ' => 'tch',
        'ხ' => 'kh','ჯ' => 'j','ჰ' => 'h'
    );
    $iso9_table = array_merge($iso9_table, $geo2lat);

    $locale = get_locale();
    switch ( $locale ) {
        case 'bg_BG':
            $iso9_table['Щ'] = 'SHT';
            $iso9_table['щ'] = 'sht';
            $iso9_table['Ъ'] = 'A';
            $iso9_table['ъ'] = 'a';
            break;
        case 'uk':
        case 'uk_ua':
        case 'uk_UA':
            $iso9_table['И'] = 'Y';
            $iso9_table['и'] = 'y';
            break;
    }

    $is_term = false;
    $backtrace = debug_backtrace();
    foreach ( $backtrace as $backtrace_entry ) {
        if ( $backtrace_entry['function'] == 'wp_insert_term' ) {
            $is_term = true;
            break;
        }
    }

    $term = $is_term ? $wpdb->get_var("SELECT slug FROM {$wpdb->terms} WHERE name = '$title'") : '';
    if ( empty($term) ) {
        $title = strtr($title, apply_filters('ctl_table', $iso9_table));
        if (function_exists('iconv')){
            $title = iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $title);
        }
        $title = preg_replace("/[^A-Za-z0-9'_\-\.]/", '-', $title);
        $title = preg_replace('/\-+/', '-', $title);
        $title = preg_replace('/^-+/', '', $title);
        $title = preg_replace('/-+$/', '', $title);
    } else {
        $title = $term;
    }

    return $title;
}

?>