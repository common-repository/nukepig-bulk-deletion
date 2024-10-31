<?php

/*
* API Functions
*/

if(!function_exists('nukepig_api_check_result_is_pass')) {
  function nukepig_api_check_result_is_pass($check_result) {
    $result = false;

    if($check_result) {
      # A list of subscriptions for this plugin...
      $pass_array = array(
        'bronze',   # to be removed soon
        'free',
        'nukepig',
        '.*?pro',
      );

      # Compare...
      $tmp_list = strtolower(':' . str_replace(',', ':', str_replace(' ', '', $check_result)) . ':');
      foreach($pass_array as $pattern) {
        $result = preg_match("/:$pattern:/", $tmp_list);
        if($result) {
          break;
        }
      }

      unset($pass_array);
    }

    return $result;
  }
}

if(!function_exists('nukepig_api_check')) {
  function nukepig_api_check() {
    $result = false;

    if($_REQUEST['blogpig_api_key']) {
      $api_key = $_REQUEST['blogpig_api_key'];
    }
    else {
      $api_key = get_option('blogpig_api_key');
    }
    if($api_key) {
      $api_check_result = get_option('blogpig_api_check_result');
      $old_api_key = get_option('blogpig_old_api_key');
      $api_key_changed = $api_key != $old_api_key;
      $yesterday = time() - 24 * 60 * 60;
      if($api_key_changed ||                                              # api key changed since the last check or
         !nukepig_api_check_result_is_pass($api_check_result) ||          # api key did not pass or
         get_option('blogpig_api_check_date') < $yesterday) {             # the last check was more than 24h ago...
        $api_check_url = "http://blogpig.com/api_check_new.php?key={$api_key}";
        if(function_exists('curl_init')) { # try for CURL first...
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_USERAGENT, "NukePiG/2.1");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_URL, $api_check_url);

          # Proxy
          if(class_exists('WP_HTTP_Proxy')) {
            $proxy = new WP_HTTP_Proxy();
            if($proxy->is_enabled() && $proxy->send_through_proxy($api_check_url)) {
              $isPHP5 = version_compare(PHP_VERSION, '5.0.0', '>=');
              if ($isPHP5) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                curl_setopt($ch, CURLOPT_PROXY, $proxy->host());
                curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port());
              }
              else {
                curl_setopt($ch, CURLOPT_PROXY, $proxy->host() .':'. $proxy->port());
              }

              if($proxy->use_authentication()) {
                if ($isPHP5) {
                  curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                }
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy->authentication());
              }
            }
          }

          $api_check_result = curl_exec($ch);
          curl_close($ch);
          unset($ch);
        }
        else {
          $reply = false;
          if(class_exists('WP_Http')) {
            $request = new WP_Http;
            $reply = $request->request($api_check_url, array('user-agent' => 'NukePiG/2.1'));
          }
          if($reply && is_array($reply)) {
            $api_check_result = $reply['body'];
          }
          else {
            $api_check_result = file_get_contents($api_check_url);
          }
        }
        update_option('blogpig_api_check_result', $api_check_result);
        update_option('blogpig_api_check_date', time());
        update_option('blogpig_old_api_key', $api_key);
      }
      $result = nukepig_api_check_result_is_pass($api_check_result);
    }
    else {
      update_option('blogpig_api_check_result', '[ no key ]');
    }

    return trim($result);
  }
}

if(!function_exists('nukepig_api_show_error')) {
  function nukepig_api_show_error() {
    $result = false;

    $api_check_result = nukepig_api_check();
    if(!$api_check_result) {
      echo '<div id="login_error" class="updated" style="padding:6px; border: solid 1px #c00; background-color: #ffebe8; ">' .
           '<strong>NukePiG Stopped :: Invalid BlogPiG API Key.</strong> Get your free key <strong><a href="http://www.blogpig.com/api_key" target="_blank">HERE</a></strong>.' .
           '</div>';
    }

    return $result;
  }
  add_action('admin_notices', 'nukepig_api_show_error');
}



if(!function_exists('nukepig_api_show_field')) {
  function nukepig_api_show_field() {
    $result = false;

    if($_POST['btnSubmitKey']) {
      update_option('blogpig_api_key', $_POST['blogpig_api_key']);
    }

    $api_key = get_option('blogpig_api_key');
    $api_check_result = nukepig_api_check();
    if($api_key) {
      $api_key_info = trim(get_option('blogpig_api_check_result'));
    }
    else {
      $api_key_info = 'no key';
    }

    global $wp_version;

    echo "
      <div class='postbox ' >
        <div class='handlediv' title='Click to toggle'><br /></div>
        <h3 class='hndle'><span>BlogPiG API Key</span></h3>
        <div class='inside'>

          <p>
            <TABLE width='100%' style='margin-top:12px;'>
              <TR>
                <TD width='20%'>
                  API Key:
                </TD>
                <TD width='80%'>
                  <INPUT type='text' name='blogpig_api_key' id = 'blogpig_api_key' value='{$api_key}' size='35' />
                  <INPUT type='submit' class='button' name='btnSubmitKey' id='btnSubmitKey' value='Save Key' />
                  <BR />
    ";
    if(!$api_check_result) {
      echo '[ <span style="color:red; ">';
    }
    else {
      echo '[ <span style="color:green; ">';
    }
    echo "
                  {$api_key_info}</span> ]
                </TD>
              </TR>
            </TABLE>
          </p>

        </div> <!--- class='inside' --->
      </div> <!--- class='postbox ' --->
    ";


    return $result;
  }
}

if(nukepig_api_check()) {

  function nukepig_restore_default_cats() {
    $result = false;

    global $wpdb;

    $sql_count = "SELECT count(DISTINCT term_id) FROM {$wpdb->terms} WHERE term_id IN (1, 2) ";
    $count = $wpdb->get_var($sql_count);
    if($count < 2) {
      $sql_terms = "INSERT IGNORE INTO {$wpdb->terms} (term_id, name, slug, term_group) VALUES (1, 'Uncategorized', 'uncategorized', 0), (2, 'Blogroll', 'blogroll', 0) ";
      $wpdb->query($sql_terms);
    }

    $sql_count = "SELECT count(DISTINCT term_id) FROM {$wpdb->term_taxonomy} WHERE term_id IN (1, 2) ";
    $count = $wpdb->get_var($sql_count);
    if($count < 2) {
      $sql_taxonomy = "INSERT IGNORE INTO {$wpdb->term_taxonomy} (term_taxonomy_id, term_id, taxonomy, description, parent, count) VALUES (NULL, 1, 'category', '', 0, 0), (NULL, 2, 'link_category', '', 0, 0)";
      $wpdb->query($sql_taxonomy);
    }

    return $result;
  }

  // Updating term taxonomy counts
  
  function nukepig_update_taxonomy_counts($taxonomies) {
    $result = false;

    global $wpdb;
    
    if($taxonomies) {
      foreach((array)$taxonomies as $taxonomy) {
        $selectSQL = "SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = '{$taxonomy}' ";
        $rows = $wpdb->get_results($selectSQL);
        if($rows) {
          $tt_ids = array();
          foreach($rows as $row) {
            array_push($tt_ids, $row->term_taxonomy_id);
          }
          if(function_exists('wp_update_term_count')) {
            wp_update_term_count($tt_ids, $taxonomy);
          }
        }
      }
    }

    return $result;
  }

}

?>
