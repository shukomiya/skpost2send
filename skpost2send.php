<?php
/*
 * Plugin Name: skpost2send
 * Plugin URI: http://devdiary.komish.com/
 * Description: 投稿の公開時にメールを送信する。
 * Author: Komiya Shuichi
 * version: 0.9.2
 * Author URI: http://devdiary.komish.com/
 */

/*
 * 2017/09/15 version 0.9.2
 *
 *・コンストラクタをphp7.1に対応
 *
 * 2017/08/14 version 0.9.1
 * 
 *・&nbsp;のみの変換処理を追加。html_entity_decodeが&nbsp;を処理しないため。
 *
 * 2017/07/28 version 0.9.0
 * 
 *・ HTMLエンティティの変換にPHP関数を利用
 *
 * 2016/07/12 version 0.8.0
 * 
 * ・<br /> を \n に置換するように変更。
 * 
 * 2016/03/06 version 0.7.1
 * 
 * ・sk_mail_check関数をskpost2send_mail_chechにリネーム。
 * 
 * ・in_send_mail を削除。
 * 　
 * ・ショートコード [mailonly]を削除。
 * 
 * 2016/03/05 version 0.7.0
 * 
 * ・in_send_mail を追加。
 * 　メール専用コンテンツの表示用。
 * 　ショートコード [mailonly]で表示できる。
 * 
 * 2015/08/09 version 0.6.1
 * 
 * ・Aタグからアドレスを抽出する正規表現を最短一致に変更。
 * 　最長一致だと href 以外のパラメータも抽出してしまうため。
 * 
 * 2015/08/08 version 0.6.0
 * 
 * ・Aタグの置換の書式を変更した。
 * <url>link_text
 * を
 * link_text
 * url
 * に変更。
 * 
 *  2014/09/03 version 0.5.0
 *
 *・送信プレビューでオプションのダイジェストがチェックされているときはダイジェストを送信するようにした。
 *・{$today} で 140914 の書式の文字列を返すようにした。
 *
 *  2013/04/06 version 0.4.0
 * 
 * ・投稿オプションで、全文送信か、ダイジェスト送信かを選択できるようにした。
 * 
 *  2013/02/05 version 0.3.0
 * 
 * ・投稿時にメールの送受信を設定できるようにした。
 * ・メール送信のアクションフックを transition_post_status に変更
 * 
 *  2012/12/17 version 0.2.4
 * 
 * ・skpost2send.exe 内に $post_post_status が auto-draft を追加。
 *      前回修正時に誤って消してしまったため。
 *      これがないと通常の投稿時にメールが送信されない。
 * 
 *  2012/12/14 version 0.2.3
 *  
 * ・skpost2send.exe 内に $post_post_status がヌルの時の条件を追加。
 *      これがないと予約投稿の時にメールが送信されないため。
 * 
 *  2012/2/8 version 0.2.2
 * 
 * ・more テキストがないときは適当な長さの文字列を切り出すようにした。
 * 
 * 2012/2/5 version 0.2.1
 * 
 * ・除外カテゴリーが機能しなかったバグを修正
 * 
 * 2012/1/10 version 0.2
 * 
 * ・クラス化した。
 * 
 * 2012/1/9 version 0.1.1
 * 
 * ・function sk_post_to_send で $post_id を返さなかったバグを修正。
 * ・htmlタグの正規表現スクリプトに\s?を追加。
 * ・３行をワードラップ時に文字が消えるバグを修正。 
 *  
 * 2012/1/8 version 0.1 
 */

$body_template_default = <<< EOF
■{\$title}  
<{\$permalink}>
    
{\$post}

■質問歓迎！

ネタになるので質問は大歓迎です。
https://komish.com/contact?ref=mail

■小宮秀一が何者かについては以下をクリック

https://komish.com/profile?ref=mail

■小宮秀一の商品

「集客の原則OTAM」
https://komish.com/otam/?ref=mail

「返信屋2007」
https://komish.com/hnsnyp/?ref=mail

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
店長養成講座 - 売れる店作り１０２４の方法
Copyright (c) {\$year} Komiya Shuichi. All rights reserved.
────────────────────────────────
発行者：小宮秀一
問い合わせ先：info@komish.com
連絡先：https://komish.com/profile
発行元サイト：https://komish.com
配信停止：http://www.mag2.com/m/0000279189.html 
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

EOF;

function skpost2send_digest_output( $atts, $content = null ) {
    return $content;
}
add_shortcode('digest','skpost2send_digest');

class skpost2send_options_t {
    private $options;
    
    function __construct() {
        $this->options = get_option( 'skpost2send_options', $this->default_options() );
    }
    
    function get_options() {
        return $this->options;
    }
    
    function default_options() {
        global $body_template_default;

        $options = array();
        $options["to"] = "";
        $options["from"] = "";
        $options["preview_to"] = "";
        $options["title_template"] = '{$blogname}:{$title}';
        $options["body_template"] = $body_template_default;
        $options["digest"] = true;
        $options["wordwrap"] = true;
        $options["wordwrap_count"] = '35';
        $options["exclude_categories"] = '201,216,219';

        return $options;
    }
    
    function update( $data ) {
        
        $options['to'] = $data['to'];
        $options['from'] = $data['from'];
        $options['preview_to'] = $data['preview_to'];
        $options['title_template'] = $data['title_template'];
        $options['body_template'] = $data['body_template'];
        if ( isset($data['digest']) && ( $data['digest'] == 'digest' ) ) {
            $options["digest"] = true;
        } else {
            $options["digest"] = false;
        }
        if ( isset($data['wordwrap']) && ( $data['wordwrap'] == 'wordwrap' )) {
            $options['wordwrap'] = true;
        } else {
            $options['wordwrap'] =  false;
        }
        $options['wordwrap_count'] = $data['wordwrap_count'];

        $options['exclude_categories'] = $data['exclude_categories'];

        update_option("skpost2send_options", $options);

        $this->options = $options;
        
        return $options;
    }
}

class html2text_t {
    
    private function from_phara( $text ){
        if ( preg_match_all( '/<p\s?.*?>(.*?)<\/p>\s*/is', $text, $matches, PREG_SET_ORDER )) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $rep = trim( $lines[1] );
                $text = str_ireplace( $line, "{$rep}\n\n", $text );
            }
        }
        return $text;
    }
    
    private function from_li( $text ) {
        if ( preg_match_all ( '/<li\s?.*?>/', $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $text = str_ireplace( $line, "-", $text );
            }
        }
        return $text;
    }

    private function from_blockquote ( $text ) {
        if ( preg_match_all ( '/<blockquote\s?.*?>/i', $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $text = str_ireplace( $line, "\n　　", $text );
            }
        }

        if ( preg_match_all ( '/<\/blockquote>/i', $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $text = str_ireplace( $line, "\n", $text );
            }
        }
         return $text;
    }

    private function from_header ( $text ) {
        if ( preg_match_all ( '/<\/h[1-6]\s?.*?>/i', $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $text = str_ireplace( $line, "\n", $text );
            }
        }

        if ( preg_match_all ( '/<h[1-6]>/i', $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $text = str_ireplace( $line, "\n●", $text );
            }
        }
        return $text;
    }

    private function from_link ( $text ) {

        $matches = null;
        
        if ( preg_match_all('/<a\s+([^>]+)>(.*)<\/a>/i', $text, $matches, PREG_SET_ORDER) ) {
            foreach ($matches as $tag) {
                $tag_all = $tag[0];
                $link_text = $tag[2];
                $link_param =$tag[1];
                $sub_matches = null;
                if ( preg_match('/href=("|\'|)(.*?)\1/', $link_param, $sub_matches) ) {
                    $link_adress = $sub_matches[2];
                    $str = "$link_text\r\n$link_adress";
                    $text = str_ireplace( $tag_all, $str, $text );
                }
            }
        }
        return $text;
    }
    
    private function from_br( $text ) {
        
        $matched = null;
        
        if ( preg_match_all('/<br\s*?(\/\s*?)?>\s*?\n/i', $text, $matches, PREG_SET_ORDER) ) {
            foreach ( $matches as $lines ) {
                $line = $lines[0];
                $text = str_ireplace( $line, "\n", $text );
            }
        }
        return $text;
    }

    function exec( $text ) {

        $text = $this->from_phara( $text );
        $text = $this->from_link( $text );
        $text = $this->from_li( $text );
        $text = $this->from_header( $text );
        $text = $this->from_blockquote( $text );
        $text = $this->from_br( $text );

		$text= str_replace('&nbsp;', ' ', $text);
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        
        $text = strip_tags( $text);

        $text = str_replace( '▼■★', '<', $text);
        $text = str_replace( '★■▼', '>', $text);

        return $text;
    }
}

class wordwrap_t {
    
    private function adjust_head( $wk, $line_count ) {

        $kinsoku[0] = '';
        $kinsoku[1] = $wk;

        for ( $i = 0; $i < 3; $i++ ) {
            $top = mb_substr( $wk, $i, 1, "UTF-8" );

            if ( mb_strpos( ",.､｡、。，．]}):;,.-?!ﾞﾟ｣･~・：；？！゛゜‐’）〕］｝〉》」』】", $top ) === false ) {
                break;
            } else {
                $kinsoku[0] .= $top;
                $kinsoku[1] = mb_substr( $wk, 1, $line_count, "UTF-8"  );
            }
        }
        return $kinsoku;
    }

    function substr_kinsoku( $line, $start, &$line_count ) {

        for ( $i = 0; $i < 3; $i++ ) {
            $wk = mb_substr( $line, $start, $line_count, "UTF-8" );

            $last = mb_substr( $wk, mb_strlen( $wk ) - 1, 1, "UTF-8" );
            if ( mb_strpos( '[{(‘“（〔［｛〈《「『【', $last ) === false ) {
                break;
            } else {
                $line_count--;
            }
        }
        return $wk;
    }

    private function adjust_url( $text ) {

        while( preg_match( "/http[^\s]+\\n[^\s]+/", $text, $m)) {
            $tmp = $m[0];
            $tmp1 = str_replace( "\n", '', $tmp);
            $text = str_replace($tmp, $tmp1, $text);
        }
        return $text;
    }

    function exec( $text, $line_count = 253 ) {

        $tmp = $text;
        $text = '';
        $new_line = array();

        $lines = preg_split( "/\n/", $tmp );

        foreach ( $lines as $line ) {
            $start = 0;
            $line_len = mb_strlen( $line, "UTF-8");
            if ( $line_len - $start > $line_count ) {
                do {
                    $len = $line_count;
                    $wk = $this->substr_kinsoku( $line, $start, $len );
                    //行頭が禁則文字か？
                    $kinsoku = $this->adjust_head( $wk, $line_count );
                    $last = array_pop( $new_line );
                    $new_line[] = $last . $kinsoku[0];
                    $new_line[] = $kinsoku[1];
                    $start += $len;
                } while ( $line_len - $start > $line_count);

                $wk = mb_substr( $line, $start, $line_count, "UTF-8" );
                $kinsoku = $this->adjust_head( $wk, $line_count );
                $last = array_pop( $new_line );
                $new_line[] = $last . $kinsoku[0];
                $new_line[] = $kinsoku[1];
            } else {
                $new_line[] = $line;
            }
        }

        foreach ( $new_line as $line ) {
            $text .= "$line\n";
        }

        $text = $this->adjust_url( $text );

        return $text;
    }
   
}

class skpost2send {
    private $plugin_name;
    private $options;
    private $exclude_categories;
    private $digest;
    
    public function __construct() {
        $this->plugin_name = 'skpost2send';
        $opt = new skpost2send_options_t();
        $this->options = $opt->get_options();
        $this->exclude_categories = explode(',', $this->options['exclude_categories']);
    }

    function option_menu_callback() {
        add_options_page( $plugin_name, $plugin_name, 'manage_options', __FILE__, array( &$this, 'option_menu' ) );
    }
    
    function get_title( $postdata ) {

        $title = html_entity_decode($postdata->post_title, ENT_QUOTES);
        
        return str_replace('{$title}', $title, $this->options["title_template"]);
    }

    function get_content( $postdata, $is_preview = false ) {

        $converter = new html2text_t();
        
        $title = $postdata->post_title;
        $post = $postdata->post_content;

        $more = '<!--more-->';

        if ( !$is_preview ) {
            $is_digest = $this->digest;
        } else {
            $is_digest = $this->options['digest'];
	}

        if ( $is_digest === TRUE ) {
            if ( stripos( $post, $more ) ) {
                $s = explode( $more, $post );
                $post = $s[0];
            } else {
                $post = mb_substr( $post, 0, 140 ) . '[...]';
            }
        }
        $post = $converter->exec( $post );
        
        if ( $this->options['wordwrap'] == true ) {
            $wordwrap = new wordwrap_t();
            $post = $wordwrap->exec( $post, $this->options['wordwrap_count'] );
        }
        
        $permalink = get_permalink( $postdata->ID );
        $today = date('ymd');
        $year = date('Y'); 
        
        $body_template = $this->options['body_template'];
        
        if ( !$is_digest ) {
            mb_internal_encoding('UTF-8');
            $pattern = '/\\[digest\\].*?\\[\\/digest\\]/s';
            $body_template = preg_replace($pattern, '', $body_template);
        } else {
            $body_template = str_replace( '[digest]', '', $body_template );
            $body_template = str_replace( '[/digest]', '', $body_template );
        }
        
        $body_template = str_replace( '{$title}', $title, $body_template );
        $body_template = str_replace( '{$post}', $post, $body_template );
        $body_template = str_replace( '{$permalink}', $permalink, $body_template );
        $body_template = str_replace( '{$today}', $today, $body_template );
        $body_template = str_replace( '{$year}', $year, $body_template );
        
        return $body_template;
    }

    function send( $postdata, $to, $from, $is_preview = false ) {
        $cat = get_the_category( $postdata->ID );
        $cat = $cat[0];
        $cat_id = $cat->cat_ID;
        
        if ( in_array($cat_id, $this->exclude_categories) === false ) {
            $title = $this->get_title( $postdata );
            $content = $this->get_content( $postdata, $is_preview );
            wp_mail($to, $title, $content, "From: $from");
        }
    }

    function get_the_lastest_ID() {
        global $wpdb;
        $row = $wpdb->get_row("SELECT ID FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC");
        return !empty( $row ) ? $row->ID : '0';
    }

    function the_latest_ID() {
        echo $this->get_the_latest_ID();
    }

    function preview() {
        
        $post_id = $this->get_the_lastest_ID();
        
        if ( $post_id ) {
            $postdata = get_post($post_id);
        
            $this->send( $postdata, $this->options["preview_to"], $this->options[ "from" ], true );
        }
    }
    
    function exec( $new_status, $old_status, $post ) {

        if (( !isset($old_status) || $old_status === 'new' || $old_status == 'pending' 
                || $old_status === 'draft' || $old_status === 'auto-draft'
                || $old_status === 'future' || $old_status === 'private' ) 
                && $new_status === 'publish') {
            if ( isset($_POST['skpost2send_is_send']) && 
                    $_POST['skpost2send_is_send'] === 'send' ) {
                $is_send = true;
            } else {
                $metadata = get_post_meta($post->ID, 'skpost2send_is_send', true);
                $is_send = (isset($metadata) && $metadata === 'send');
            }
            
            if ( isset($_POST['skpost2send_digest']) ) {
                $this->digest = $_POST['skpost2send_digest'] == 'digest' ;
            } else {
                $metadata = get_post_meta($post->ID, 'skpost2send_digest', true);
                $this->digest = (isset($metadata) && $metadata == 'digest');
            }
            
            if ($is_send) {
                $this->send( $post, $this->options["to"], $this->options["from"] );
                delete_post_meta($post->ID, 'skpost2send_is_send');
            }
        }
    }
}

function skpost2send_exec( $new_status, $old_status, $post ) {
    
    $sps = new skpost2send();

    $sps->exec( $new_status, $old_status, $post );
}

add_action('transition_post_status', 'skpost2send_exec', 10, 3);

//--------------------------------------------------------------------------
//
//  管理画面>設定>Exsampleプラグインページを追加
//
//--------------------------------------------------------------------------
  
class skpost2send_admin_menu_t {
    private $opt;
    
    function __construct() {
        $this->opt = new skpost2send_options_t();
    }
    
    function exec() {
        if ( isset( $_POST['save'] ) ) {
            $options = $this->opt->update( $_POST );
            echo '<div class="updated"><p><strong>保存しました</strong></p></div>';
        } else if( isset( $_POST['preview'] ) ){
            $sps = new skpost2send();
            $sps->preview();
            $options = $this->opt->get_options();
            echo '<div class="updated"><p><strong>プレビューを送信しました</strong></p></div>';
        } else {
            $options = $this->opt->get_options();
        }
        // 設定変更画面を表示する
        ?>
        <div class="wrap">
            <h2>skpost2send</h2>
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">宛先メールアドレス:</th>
                        <td><input type="text" name="to" value="<?php echo $options["to"]; ?>" size="60" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">プレビュー宛先アドレス:</th>
                        <td><input type="text" name="preview_to" value="<?php echo $options["preview_to"]; ?>" size="60" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">送信元メールアドレス:</th>
                        <td><input type="text" name="from" value="<?php echo $options["from"]; ?>" size="60" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">件名テンプレート:</th>
                        <td><input type="text" name="title_template" value="<?php echo $options["title_template"]; ?>" size="80" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">本文テンプレート:</th>
                        <td><textarea name="body_template" cols="90" rows="10"><?php echo $options["body_template"]; ?></textarea></td>
                    </tr>
                    <tr>
                        <th valign="top">折り返しする:</th>
                        <td><input type="checkbox" name="wordwrap" 
                                   value="wordwrap" <?php if ($options['wordwrap']){echo 'checked';} ?> /></td>
                    </tr>
                    <tr>
                        <th valign="top">折り返し文字数:</th>
                        <td><input type="text" name="wordwrap_count" value="<?php echo $options["wordwrap_count"]; ?>"/></td>
                    </tr>
                    <tr>
                        <th valign="top">除外するカテゴリーID（カンマ区切り）:</th>
                        <td><input type="text" name="exclude_categories" value="<?php echo $options["exclude_categories"]; ?>" /></td>
                    </tr>
                    <tr>
                        <th valign="top">ダイジェスト:</th>
                        <td><input type="checkbox" name="digest" 
                                   value="digest" <?php if ($options['digest']){ echo 'checked'; } ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">プレビュー</th>
                        <td><input class="button-secondary" type="submit" name ="preview" value="送信プレビュー" /></td>
                    </tr>
                </table>
                <p class="submit">
            <input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
        </div>
        <?php
    }
}

/* 投稿・固定ページの "advanced" 画面にカスタムセクションを追加 */
function skpost2send_add_custom_box() {

    if( function_exists( 'add_meta_box' )) {
        add_meta_box( 'skpost2send_sectionid', 'skpost2send', 'skpost2send_inner_custom_box', 'post', 'advanced' );
    }
}

/* カスタム投稿・固定ページセクションに内側のフィールドをプリント */
function skpost2send_inner_custom_box() {
    global $post;
    
    $opt = new skpost2send_options_t();
    $options = $opt->get_options();
    
    // 認証に nonce を使う
    echo '<input type="hidden" name="skpost2send_noncename" id="skpost2send_noncename" value="' . 
      wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

    // データ入力用の実際のフォーム

    echo '<p><label for="skpost2send_is_send">' . 'メールを送信する ' . '</label> ';
    $data = get_post_meta($post->ID, 'skpost2send_is_send', true);
    if ($data == 'send') {
        echo '<input type="checkbox" name="skpost2send_is_send" checked="checked" value="send" />';
    } else {
        echo '<input type="checkbox" name="skpost2send_is_send" value="send" />';
    }
    echo '</p><p>';
    
    echo '<label for="skpost2send_digest">' . 'ダイジェストを送信 ' . '</label> ';
    $digest = get_post_meta($post->ID, "skpost2send_digest",  true);
    
    if ($digest == 'digest' ) {
        echo '<input type="checkbox" name="skpost2send_digest" checked="checked" value="digest" />';
    } else {
        echo '<input type="checkbox" name="skpost2send_digest" value="digest" />';
    } 
    echo '</p>';
}

/* 投稿を保存した際、カスタムデータも保存する */
function skpost2send_save_postdata( $post_id ) {

    // データが先ほど作った編集フォームのから適切な認証とともに送られてきたかどうかを確認。
    // save_post は他の時にも起動する場合がある。

    if ( !wp_verify_nonce( $_POST['skpost2send_noncename'], plugin_basename(__FILE__) )) {
        return $post_id;
    }

    // 自動保存ルーチンかどうかチェック。そうだった場合はフォームを送信しない（何もしない）
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
        return $post_id;

    // パーミッションチェック
    if ( !current_user_can( 'edit_post', $post_id ) ) {
        return $post_id;
    }

    // 承認ができたのでデータを探して保存

    $is_send = $_POST['skpost2send_is_send'];
    $digest = $_POST['skpost2send_digest'];

    // $mydata を使って何かを行う
    // （add_post_meta()、update_post_meta()、またはカスタムテーブルを使うなど）

    update_post_meta($post_id, 'skpost2send_is_send', $is_send);
    update_post_meta($post_id, 'skpost2send_digest', $digest);

     return $mydata;
}    

function skpost2send_options_menu() {
    
    $admin = new skpost2send_admin_menu_t();
    
    $admin->exec();
}

/* admin_menu アクションフックでカスタムボックスを定義 */
add_action('admin_menu', 'skpost2send_add_custom_box');

/* データが入力された際 save_post アクションフックを使って何か行う */
add_action('save_post', 'skpost2send_save_postdata');

// アクションフックのコールバッック関数
function add_skpost2send_admin_menu() {
    // 設定メニュー下にサブメニューを追加:
    add_options_page('skpost2send', 'skpost2send', 'manage_options', __FILE__, 'skpost2send_options_menu');
}

// 管理メニューのアクションフック
add_action('admin_menu', 'add_skpost2send_admin_menu');
  
//--------------------------------------------------------------------------
//
//  プラグイン削除の際に行うオプションの削除
//
//--------------------------------------------------------------------------
if ( function_exists('register_uninstall_hook') ) {
    register_uninstall_hook(__FILE__, 'uninstall_hook_skpost2send');
}
function uninstall_hook_skpost2send () {
    delete_option('skpost2send_options');
}

?>
