<?php

  global $wp_version;
  #$wp_version = '2.5';

  global $wpdb;
  @set_time_limit(43200); # 12 hours

  $proceed = true;
  $my_product = 'nukepig';
  # Removing old reg functions...
  #include "reg_check.php";

  if($proceed) {
    $options = array(
                    'wpnukepig_mu_blogs'                    => 'no',
                    'wpnukepig_mu_blog_content'             => 'this',
                    
                    'wpnukepig_pages'                       => 'no',
                    'wpnukepig_pages_status'                => 'all',

                    'wpnukepig_posts'                       => 'no',
                    'wpnukepig_posts_status'                => 'all',

                    'wpnukepig_revisions'                   => 'no',
                    'wpnukepig_pages_revisions'             => 'no',
                    'wpnukepig_posts_revisions'             => 'no',

                    'wpnukepig_custom_fields'               => 'no',
                    'wpnukepig_custom_fields_name'          => 'all',
                    'wpnukepig_pages_custom_fields'         => 'no',
                    'wpnukepig_pages_custom_fields_name'    => 'all',
                    'wpnukepig_posts_custom_fields'         => 'no',
                    'wpnukepig_posts_custom_fields_name'    => 'all',

                    'wpnukepig_tags'                        => 'no',
                    'wpnukepig_categories'                  => 'no',

                    'wpnukepig_comments'                    => 'no',
                    'wpnukepig_comments_status'             => 'all',
                    'wpnukepig_comments_commentpig'         => 'no',
                    'wpnukepig_comments_commentpig_status'  => 'all',

                    'wpnukepig_links'                       => 'no',
                    'wpnukepig_link_categories'             => 'no',
                    );
    $nuked_stats = array();

    $plugin_dir = basename(dirname(__FILE__)) . "/";
    $plugin_file = "wpnukepig.php";
    $plugin_name = $plugin_dir . $plugin_file;
    $plugin_url = get_option('siteurl') . "/wp-content/plugins/{$plugin_dir}";
    $config_url = "?page={$plugin_name}";
    $settings_url = get_option('siteurl') . "/wp-admin/admin.php{$config_url}";
    $my_version = 'unknown';
    $plugins = get_plugins();
    if(is_array($plugins)) {
      $my_version = $plugins[$plugin_dir . $plugin_file]['Version'];
      $plugins_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
      $my_version = wp_kses($my_version, $plugins_allowedtags);
      unset($plugins_allowedtags);
    }
  ?>
  
  
    <?php

    if(nukepig_api_check()) {

      if(function_exists('nukepig_restore_default_cats')) {
        nukepig_restore_default_cats();
      }

      # Other updates...
      if (isset($_POST['wpnukepig_submit'])) {
        if (function_exists('current_user_can') && !current_user_can('manage_options'))
          die(__('What are you doing here?!'));
  
        foreach($options as $key => $default) {
          $value = $_POST[$key];
          if(isset($value)) {
            update_option($key, $value);
          }
          else {
            update_option($key, $default);
          }
        }
  
        if($_POST['wpnukepig_mu_blogs'] == 'yes') {
          # Nuke all blogs...
          $cnt = 0;
          if(function_exists('wpmu_delete_blog')) {
            $sql_select = "SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > 1 ORDER BY blog_id ";
            $wpdb->hide_errors();
            $blog_rows = $wpdb->get_results($sql_select);
            $wpdb->show_errors();
            if($blog_rows && !is_wp_error($blog_rows)) {
              foreach($blog_rows as $row) {
                wpmu_delete_blog($row->blog_id);
                $cnt++;
              }
            }
            unset($blog_rows);
          }
          $nuked_stats['mu_blogs']['blogs nuked'] = $cnt;
        }
        else {
          # No need to nuke content if we're nuking complete blogs :)
  
          $blogs_array = array(1); # the default blog - this one...
          if($_POST['wpnukepig_mu_blog_content'] == 'all') {
            # Nuke content for ALL blogs...
            $sql_select = "SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > 1 ORDER BY blog_id ";
            $wpdb->hide_errors();
            $blog_rows = $wpdb->get_results($sql_select);
            $wpdb->show_errors();
            if($blog_rows && !is_wp_error($blog_rows)) {
              foreach($blog_rows as $row) {
                array_push($blogs_array, $row->blog_id);
              }
            }
            unset($blog_rows);
          }
          $nuked_stats['mu_blog_content']['blogs selected'] = count($blogs_array);


          /*
           * Iterate through all blogs...
           */

          foreach($blogs_array as $blog_id) {
            if(function_exists('switch_to_blog')) {
              switch_to_blog($blog_id);
            }

            /*
             * Pages
             */

            if($_POST['wpnukepig_pages_revisions'] == 'yes') {
              $page_ids = '';
              # Get ALL page IDs...
              $selectSQL = "SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_type = 'page'";
              $rows = $wpdb->get_results($selectSQL);
              if($rows) {
                foreach($rows as $row) {
                  if($page_ids) {
                    $page_ids .= ',';
                  }
                  $page_ids .= $row->ID;
                }
              }
              $nuked_stats['pages_revisions']['page revisions nuked'] = 0;
              if($page_ids) {
                # Nuke ALL page revisions...
                $nukeSQL = "DELETE FROM `{$wpdb->posts}` WHERE post_type = 'revision' AND post_parent IN ({$page_ids}) ";
                $wpdb->query($nukeSQL);
                $nuked_stats['pages_revisions']['page revisions nuked'] = $wpdb->rows_affected;
              }
            }

            if($_POST['wpnukepig_pages'] == 'yes') {
              # Nuke meta for ALL pages...
              $nukeSQL = "DELETE FROM `{$wpdb->postmeta}` WHERE post_id IN (SELECT ID FROM `{$wpdb->posts}` WHERE post_type = 'page' ";
              if($_POST['wpnukepig_pages_status'] && $_POST['wpnukepig_pages_status'] != 'all') {
                $nukeSQL .= " AND post_status = '" . $_POST['wpnukepig_pages_status'] . "' ";
              }
              $nukeSQL .= " ) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['pages']['meta nuked'] = $wpdb->rows_affected;
              # Nuke future publish events for ALL pages...
              if($_POST['wpnukepig_pages_status'] || $_POST['wpnukepig_pages_status'] == 'all' || $_POST['wpnukepig_pages_status'] == 'future') {
                $selectSQL = "SELECT ID FROM `{$wpdb->posts}` WHERE post_status = 'future' AND post_type = 'page' ";
                $rows = $wpdb->get_results($selectSQL);
                $un_count = 0;
                foreach($rows as $row) {
                  $args = array((int)$row->ID);
                  $next_run = wp_next_scheduled('publish_future_post', $args);
                  while($next_run) {
                    wp_unschedule_event($next_run, 'publish_future_post', $args);
                    $un_count++;
                    $next_run = wp_next_scheduled('publish_future_post', $args);
                  }
                }
                $nuked_stats['pages']['future pages unscheduled'] = $un_count;
              }
              # Nuke ALL pages...
              $nukeSQL = "DELETE FROM `{$wpdb->posts}` WHERE post_type = 'page' ";
              if($_POST['wpnukepig_pages_status'] && $_POST['wpnukepig_pages_status'] != 'all') {
                $nukeSQL .= " AND post_status = '" . $_POST['wpnukepig_pages_status'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['pages']['pages nuked'] = $wpdb->rows_affected;
            }

            if($_POST['wpnukepig_pages_custom_fields'] == 'yes') {
              # Nuke ALL page custom fields...
              $nukeSQL = "DELETE FROM `{$wpdb->postmeta}` WHERE post_id IN (SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_type = 'page') AND meta_key NOT LIKE '\\_%' ";
              if($_POST['wpnukepig_pages_custom_fields_name'] && $_POST['wpnukepig_pages_custom_fields_name'] != 'all') {
                $nukeSQL .= " AND meta_key = '" . $_POST['wpnukepig_pages_custom_fields_name'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['pages_custom_fields']['custom fields nuked'] = $wpdb->rows_affected;
            }


            /*
             * Posts
             */

            if($_POST['wpnukepig_posts_revisions'] == 'yes') {
              $post_ids = '';
              # Get ALL post IDs...
              $selectSQL = "SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_type = 'post'";
              $rows = $wpdb->get_results($selectSQL);
              if($rows) {
                foreach($rows as $row) {
                  if($post_ids) {
                    $post_ids .= ',';
                  }
                  $post_ids .= $row->ID;
                }
              }
              $nuked_stats['posts_revisions']['term relationships nuked'] = 0;
              $nuked_stats['posts_revisions']['post revisions nuked'] = 0;
              if($post_ids) {
                # Nuke term relationships for ALL post revisions...
                $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` " .
                           "WHERE " .
                           "  object_id IN " .
                           "    (SELECT DISTINCT ID FROM `{$wpdb->posts}` " .
                           "     WHERE post_parent IN ({$post_ids})) AND " .
                           "  term_taxonomy_id IN " .
                           "    (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` " .
                           "     WHERE taxonomy IN ('category', 'post_tag')) ";
                $wpdb->query($nukeSQL);
                $nuked_stats['posts_revisions']['term relationships nuked'] = $wpdb->rows_affected;

                # Nuke ALL post revisions...
                $nukeSQL = "DELETE FROM `{$wpdb->posts}` WHERE post_type = 'revision' AND post_parent IN ({$post_ids}) ";
                $wpdb->query($nukeSQL);
                $nuked_stats['posts_revisions']['post revisions nuked'] = $wpdb->rows_affected;
              }
            }

            if($_POST['wpnukepig_posts'] == 'yes') {
              # Do we have to get a different list of post IDs?
              $selectSQL = "SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_type = 'post' ";
              if($_POST['wpnukepig_posts_status'] && $_POST['wpnukepig_posts_status'] != 'all') {
                $selectSQL .= " AND post_status = '" . $_POST['wpnukepig_posts_status'] . "' ";
              }
              # Nuke term relationships for ALL posts...
              $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` WHERE ";
              if($_POST['wpnukepig_posts_status'] && $_POST['wpnukepig_posts_status'] != 'all') {
                $nukeSQL .= " object_id IN ({$selectSQL}) AND ";
              }
              $nukeSQL .= " term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy IN ('category', 'post_tag')) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['posts']['term relationships nuked'] = $wpdb->rows_affected;
              # Nuke meta for ALL posts...
              $nukeSQL = "DELETE FROM `{$wpdb->postmeta}` WHERE post_id IN ({$selectSQL}) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['posts']['meta nuked'] = $wpdb->rows_affected;
              # Nuke future publish events for ALL posts...
              if($_POST['wpnukepig_posts_status'] || $_POST['wpnukepig_posts_status'] == 'all' || $_POST['wpnukepig_posts_status'] == 'future') {
                $selectSQL = "SELECT ID FROM `{$wpdb->posts}` WHERE post_status = 'future' AND post_type = 'post' ";
                $rows = $wpdb->get_results($selectSQL);
                $un_count = 0;
                foreach($rows as $row) {
                  $args = array((int)$row->ID);
                  $next_run = wp_next_scheduled('publish_future_post', $args);
                  while($next_run) {
                    wp_unschedule_event($next_run, 'publish_future_post', $args);
                    $un_count++;
                    $next_run = wp_next_scheduled('publish_future_post', $args);
                  }
                }
                $nuked_stats['posts']['future posts unscheduled'] = $un_count;
              }
              # Nuke ALL posts...
              $nukeSQL = "DELETE FROM `{$wpdb->posts}` WHERE post_type = 'post' ";
              if($_POST['wpnukepig_posts_status'] && $_POST['wpnukepig_posts_status'] != 'all') {
                $nukeSQL .= " AND post_status = '" . $_POST['wpnukepig_posts_status'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['posts']['posts nuked'] = $wpdb->rows_affected;
              # Nuke ALL schedules...
              wp_clear_scheduled_hook('publish_future_post');

              // Update taxonomy counts...
              nukepig_update_taxonomy_counts(array('category', 'post_tag'));

            }

            if($_POST['wpnukepig_posts_custom_fields'] == 'yes') {
              # Nuke ALL post custom fields...
              $nukeSQL = "DELETE FROM `{$wpdb->postmeta}` WHERE post_id IN (SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_type = 'post') AND meta_key NOT LIKE '\\_%' ";
              if($_POST['wpnukepig_posts_custom_fields_name'] && $_POST['wpnukepig_posts_custom_fields_name'] != 'all') {
                $nukeSQL .= " AND meta_key = '" . $_POST['wpnukepig_posts_custom_fields_name'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['posts_custom_fields']['custom fields nuked'] = $wpdb->rows_affected;
            }


            /*
             * Revisions
             */

            if($_POST['wpnukepig_revisions'] == 'yes') {
              # Nuke term relationships for ALL post revisions...
              $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` WHERE object_id IN (SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_type = 'revision') AND term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy IN ('category', 'post_tag')) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['revisions']['term relationships nuked'] = $wpdb->rows_affected;

              # Nuke ALL post revisions...
              $nukeSQL = "DELETE FROM `{$wpdb->posts}` WHERE post_type = 'revision' ";
              $wpdb->query($nukeSQL);
              $nuked_stats['revisions']['revisions nuked'] = $wpdb->rows_affected;
            }


            /*
             * Custom fields
             */

            if($_POST['wpnukepig_custom_fields'] == 'yes') {
              # Nuke ALL custom fields...
              $nukeSQL = "DELETE FROM `{$wpdb->postmeta}` WHERE meta_key NOT LIKE '\\_%' ";
              if($_POST['wpnukepig_custom_fields_name'] && $_POST['wpnukepig_custom_fields_name'] != 'all') {
                $nukeSQL .= " AND meta_key = '" . $_POST['wpnukepig_custom_fields_name'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['custom_fields']['custom fields nuked'] = $wpdb->rows_affected;
            }


            /*
             * Tags
             */

            if($_POST['wpnukepig_tags'] == 'yes') {
              # Nuke term relationships for ALL tags...
              $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'post_tag') ";
              $wpdb->query($nukeSQL);
              $nuked_stats['tags']['term relationships nuked'] = $wpdb->rows_affected;
              # Nuke terms for ALL tags...
              $nukeSQL = "DELETE FROM `{$wpdb->terms}` WHERE term_id IN (SELECT tt1.term_id FROM `{$wpdb->term_taxonomy}` tt1 LEFT JOIN `{$wpdb->term_taxonomy}` tt2 ON tt2.term_id = tt1.term_id AND tt2.taxonomy <> 'post_tag' WHERE tt1.taxonomy = 'post_tag' AND tt2.taxonomy IS NULL) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['tags']['terms nuked'] = $wpdb->rows_affected;
              # Nuke term taxonomy for ALL tags...
              $nukeSQL = "DELETE FROM `{$wpdb->term_taxonomy}` WHERE taxonomy = 'post_tag' ";
              $wpdb->query($nukeSQL);
              $nuked_stats['tags']['tags nuked'] = $wpdb->rows_affected;
            }


            /*
             * Categories
             */

            if($_POST['wpnukepig_categories'] == 'yes') {
              # Nuke term relationships for ALL categories...
              $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE term_id > 2 AND taxonomy = 'category') ";
              $wpdb->query($nukeSQL);
              $nuked_stats['categories']['term relationships nuked'] = $wpdb->rows_affected;
              # Nuke terms for ALL categories...
              $nukeSQL = "DELETE FROM `{$wpdb->terms}` WHERE term_id > 2 AND term_id IN (SELECT tt1.term_id FROM `{$wpdb->term_taxonomy}` tt1 LEFT JOIN `{$wpdb->term_taxonomy}` tt2 ON tt2.term_id = tt1.term_id AND tt2.taxonomy <> 'category' WHERE tt1.taxonomy = 'category' AND tt2.taxonomy IS NULL) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['categories']['terms nuked'] = $wpdb->rows_affected;
              # Nuke term taxonomy for ALL categories...
              $nukeSQL = "DELETE FROM `{$wpdb->term_taxonomy}` WHERE term_id > 2 AND taxonomy = 'category' ";
              $wpdb->query($nukeSQL);
              $nuked_stats['categories']['categories nuked'] = $wpdb->rows_affected;
            }


            /*
             * Links (part 1)
             */

            if($_POST['wpnukepig_link_categories'] == 'yes') {
              # Nuke term relationships for ALL link categories...
              $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE term_id > 2 AND taxonomy = 'link_category') ";
              $wpdb->query($nukeSQL);
              $nuked_stats['link_categories']['term relationships nuked'] = $wpdb->rows_affected;
              # Nuke terms for ALL link categories...
              $nukeSQL = "DELETE FROM `{$wpdb->terms}` WHERE term_id > 2 AND term_id IN (SELECT tt1.term_id FROM `{$wpdb->term_taxonomy}` tt1 LEFT JOIN `{$wpdb->term_taxonomy}` tt2 ON tt2.term_id = tt1.term_id AND tt2.taxonomy <> 'link_category' WHERE tt1.taxonomy = 'link_category' AND tt2.taxonomy IS NULL) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['link_categories']['terms nuked'] = $wpdb->rows_affected;
              # Nuke term taxonomy for ALL link categories...
              $nukeSQL = "DELETE FROM `{$wpdb->term_taxonomy}` WHERE term_id > 2 AND taxonomy = 'link_category' ";
              $wpdb->query($nukeSQL);
              $nuked_stats['link_categories']['link categories nuked'] = $wpdb->rows_affected;
            }


            /*
             * Comments
             */

            if($_POST['wpnukepig_comments'] == 'yes') {
              # Nuke ALL comments...
              $nukeSQL = "DELETE FROM `{$wpdb->comments}` ";
              if($_POST['wpnukepig_comments_status'] != '' && $_POST['wpnukepig_comments_status'] != 'all') {
                $nukeSQL .= " WHERE comment_approved = '" . $_POST['wpnukepig_comments_status'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['comments']['comments nuked'] = $wpdb->rows_affected;
              # Update the number of comments...
              $nukeSQL = "UPDATE `{$wpdb->posts}` SET comment_count = (SELECT COUNT(DISTINCT comment_ID) FROM `{$wpdb->comments}` WHERE comment_post_ID = `{$wpdb->posts}`.ID) ";
              $wpdb->query($nukeSQL);
            }
            else if($_POST['wpnukepig_comments_commentpig'] == 'yes') {
              # Nuke ALL CommentPiG comments...
              $nukeSQL = "DELETE FROM `{$wpdb->comments}` WHERE comment_author_email LIKE '%commentpig.com' ";
              if($_POST['wpnukepig_comments_commentpig_status'] != '' && $_POST['wpnukepig_comments_commentpig_status'] != 'all') {
                $nukeSQL .= " AND comment_approved = '" . $_POST['wpnukepig_comments_commentpig_status'] . "' ";
              }
              $wpdb->query($nukeSQL);
              $nuked_stats['comments_commentpig']['CommentPiG comments nuked'] = $wpdb->rows_affected;
              # Update the number of comments...
              $nukeSQL = "UPDATE `{$wpdb->posts}` SET comment_count = (SELECT COUNT(DISTINCT comment_ID) FROM `{$wpdb->comments}` WHERE comment_post_ID = `{$wpdb->posts}`.ID) ";
              $wpdb->query($nukeSQL);
            }


            /*
             * Links (part 2)
             */

            if($_POST['wpnukepig_links'] == 'yes') {
              # Nuke term relationships for ALL links...
              $nukeSQL = "DELETE FROM `{$wpdb->term_relationships}` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM `{$wpdb->term_taxonomy}` WHERE taxonomy IN ('link_category')) ";
              $wpdb->query($nukeSQL);
              $nuked_stats['links']['term relationships nuked'] = $wpdb->rows_affected;
              /* Nuke term taxonomy for ALL links...
              $nukeSQL = "DELETE FROM `{$wpdb->term_taxonomy}` WHERE taxonomy in ('link_category') ";
              $wpdb->query($nukeSQL);
              $nuked_stats['links']['term taxonomy nuked'] = $wpdb->rows_affected; */
              # Nuke ALL links...
              $nukeSQL = "DELETE FROM `{$wpdb->links}` ";
              $wpdb->query($nukeSQL);
              $nuked_stats['links']['links nuked'] = $wpdb->rows_affected;

              // Update taxonomy counts...
              nukepig_update_taxonomy_counts('link_category');

            }
          
            if(function_exists('restore_current_blog')) {
              restore_current_blog();
            }
          }
    
          unset($blogs_array);
          
        }
      ?>
        
      <div id='message' class='updated fade' style='padding:4px;'><strong><?php _e(' Tactical nuke detonated.') ?></strong></div>
        
      <?php  
      }
  
    }

    # Get the options...
    $my_options = array();
    foreach($options as $key => $default) {
      $value = get_option($key);
      if(isset($value) && $value != '') {
        $my_options[$key] = $value;
      }
      else {
        $my_options[$key] = $default;
      }
    }

    $plugin_url = get_option('siteurl') . "/wp-content/plugins/{$plugin_dir}";
    $config_url = "?page={$plugin_dir}/wpnukepig.php";

    ?>
    
    <div class="wrap">
      <div id="icon-plugins" class="icon32"><br /></div>
      <h2>NukePiG</h2>
  
      <form action="<?php echo $config_url; ?>" method="post" id="wpnukepig-conf" enctype="multipart/form-data">
  
        <div id="poststuff" class="metabox-holder has-right-sidebar">
        
          <div id="side-info-column" class="inner-sidebar">
          
            <div id='side-sortables' class='meta-box-sortables'>
            
              <?php 
                $http = new WP_Http;
              ?>
            
              <div id="pagesubmitdiv" class="postbox " >
                <div class="handlediv" title="Click to toggle"><br /></div>
                <h3 class='hndle'><span>Status</span></h3>
  
                <div class="inside">
                
                  <div class="submitbox" id="submitpage">
  
                    <div id="minor-publishing">
                      <div id="misc-publishing-actions">
                      
                        <div class="misc-pub-section">
                          <label for="post_status">Your NukePiG Version:</label> 
                          <b><span id="post-status-display"><?php echo $my_version; ?></span></b>
                        </div>
                        
                        <div class="misc-pub-section misc-pub-section-last">
                          <label for="post_status">Current NukePiG Version:</label> 
                          <b><span id="post-status-display">
                          <?php
                            if($http) {
                              $reply = $http->request('http://www.blogpig.com/includes/version.php?p=' . $my_product);
                              echo ($reply && is_array($reply) ? $reply['body'] : '');
                            }
                          ?>
                          </span></b>
                        </div>
                        
                      </div> <!--- id="misc-publishing-actions" --->
                    </div> <!--- id="minor-publishing" --->
  
                  </div> <!--- id="submitpage" --->
                  
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->
              
              <div id="pageparentdiv" class="postbox " >
                <div class="handlediv" title="Click to toggle"><br /></div>
                <h3 class='hndle'><span>BlogPiG Members</span></h3>
                
                <div class="inside">
                  <?php
                    if($http) {
                      $reply = $http->request('http://www.blogpig.com/includes/members.php');
                      echo ($reply && is_array($reply) ? $reply['body'] : '');
                    }
                  ?>
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->
              
              <div id="pageparentdiv" class="postbox " >
                <div class="handlediv" title="Click to toggle"><br /></div>
                <h3 class='hndle'><span>BlogPiG News</span></h3>
                
                <div class="inside">
                  <p>
                    <ul>
                      <?php
                        if($http) {
                          $reply = $http->request('http://blogpig.com/feed');
                          if($reply && is_array($reply)) {
                            $news = array();
                            $news_count = preg_match_all('/<title>(.*?)<\/title>.*?<link>(.*?)<\/link>/is', $reply['body'], $news);
                            if($news_count > 1) {
                              $idx = 1;
                              while($idx < count($news[0])) {
                                echo '<li><a href="' . $news[2][$idx] . '">' . $news[1][$idx]. '</a></li>';
                                $idx++;
                              }
                            }
                          }
                          
                        }
                      ?>
                    </ul>
                  </p>
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->
              
              <div id="pageparentdiv" class="postbox " >
                <div class="handlediv" title="Click to toggle"><br /></div>
                <h3 class='hndle'><span>BlogPiG Software</span></h3>
                <div class="inside">
                  <p>
                    <ul>
                      <?php
                        if($http) {
                          $reply = $http->request('http://blogpig.com/products');
                          if($reply && is_array($reply)) {
                            $products = array();
                            $products_count = preg_match_all('/<h2>(.*?)<\/h2>.*?<a href=\"(.*?)\"><img/is', $reply['body'], $products);
                            if($products_count) {
                              $idx = 0;
                              while($idx < count($products[0])) {
                                echo '<li><a href="http://blogpig.com' . $products[2][$idx] . '">' . $products[1][$idx]. '</a></li>';
                                $idx++;
                              }
                            }
                          }
                          
                        }
                      ?>
                    </ul>
                  </p>
                </div> <!--- class="inside" --->
              </div> <!--- class="postbox " --->
              
              <?php 
                unset($http);
              ?>
              
            </div> <!---  class='meta-box-sortables' --->
            
          </div> <!--- class="inner-sidebar" --->
          
          
          
          <div id="post-body" class="has-sidebar">
          
            <div id="post-body-content" class="has-sidebar-content">
  
              <div id='normal-sortables' class='meta-box-sortables'>
              
                <!--- Adding new reg functions --->
                <?php nukepig_api_show_field(); ?>
              
              
                <!-- Display MU or not? --->
                <?php if($wpdb->blogid && $wpdb->blogid == 1): ?>
                
                  <div id="pagecommentstatusdiv" class="postbox " >
                    <div class="handlediv" title="Click to toggle"><br /></div>
                    <h3 class='hndle'><span>MU</span></h3>
                    <div class="inside">
                    
                      <p>
                        <TABLE width="100%" style="margin-top:12px;">
                          <TR valign="top">
                            <TD width="40%">
                              Nuke All Blogs:
                            </TD>
                            <TD width="60%">
                              <INPUT type="checkbox" name="wpnukepig_mu_blogs" id="wpnukepig_mu_blogs" value="yes" <?php if($my_options['wpnukepig_mu_blogs'] == 'yes') echo "checked"; ?> >
                              <P style="font-size:80%; margin-top:0px;">
                                This will nuke all blogs (not just the content); 
                                <?php
                                  if(isset($nuked_stats['mu_blogs'])) {
                                    foreach($nuked_stats['mu_blogs'] as $param => $value) {
                                      echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                    }
                                  }
                                ?>
                              </P>
                            </TD>
                          </TR>
          
                          <TR valign="top">
                            <TD width="40%">
                              Nuke Content for:
                            </TD>
                            <TD width="60%">
                              <INPUT type="radio" name="wpnukepig_mu_blog_content" id="wpnukepig_mu_blog_content" value="this" <?php if($my_options['wpnukepig_mu_blog_content'] == 'this') echo "checked"; ?> >
                                <LABEL for="wpnukepig_mu_blog_content">this blog</LABEL>
                                <BR />
                              <INPUT type="radio" name="wpnukepig_mu_blog_content" id="wpnukepig_mu_blog_content" value="all" <?php if($my_options['wpnukepig_mu_blog_content'] == 'all') echo "checked"; ?> >
                                <LABEL for="wpnukepig_mu_blog_content">all blogs</LABEL>
                                <BR />
                              <P style="font-size:80%; margin-top:0px;">
                                Choose whether to nuke content for this blog only or for all blogs; 
                                <BR />
                                <?php
                                  if(isset($nuked_stats['mu_blog_content'])) {
                                    foreach($nuked_stats['mu_blog_content'] as $param => $value) {
                                      echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                    }
                                  }
                                ?>
                              </P>
                            </TD>
                          </TR>
          
                        </TABLE>
                      </p>
                    
                    
                      
                    </div> <!--- class="inside" --->
                  </div> <!--- class="postbox " --->
                  
                <?php endif; ?>
                
                <!--- Main content... --->
                <div id="pagecommentstatusdiv" class="postbox " >
                  <div class="handlediv" title="Click to toggle"><br /></div>
                  <h3 class='hndle'><span>Content</span></h3>
                  <div class="inside">
                    <p>Use with care as these actions <STRONG>CANNOT BE UNDONE!</STRONG> Once nuked, the content <STRONG>CANNOT BE BROUGHT BACK!</STRONG></p>
                    
                    <p>
                      <TABLE width="100%" style="margin-top:12px;">
                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Pages:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_pages" id="wpnukepig_pages" value="yes" <?php if($my_options['wpnukepig_pages'] == 'yes') echo "checked"; ?> >
                              having status:
                              <SELECT  name="wpnukepig_pages_status" id="wpnukepig_pages_status">
                                <OPTION value="all" <?php if($my_options['wpnukepig_pages_status'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <OPTION value="publish" <?php if($my_options['wpnukepig_pages_status'] == 'publish') echo "selected"; ?> >published</OPTION>
                                <OPTION value="future" <?php if($my_options['wpnukepig_pages_status'] == 'future') echo "selected"; ?> >scheduled</OPTION>
                                <OPTION value="pending" <?php if($my_options['wpnukepig_pages_status'] == 'pending') echo "selected"; ?> >pending review</OPTION>
                                <OPTION value="draft" <?php if($my_options['wpnukepig_pages_status'] == 'draft') echo "selected"; ?> >draft</OPTION>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['pages'])) {
                                  foreach($nuked_stats['pages'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Posts:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_posts" id="wpnukepig_posts" value="yes" <?php if($my_options['wpnukepig_posts'] == 'yes') echo "checked"; ?> >
                              having status:
                              <SELECT  name="wpnukepig_posts_status" id="wpnukepig_posts_status">
                                <OPTION value="all" <?php if($my_options['wpnukepig_posts_status'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <OPTION value="publish" <?php if($my_options['wpnukepig_posts_status'] == 'publish') echo "selected"; ?> >published</OPTION>
                                <OPTION value="future" <?php if($my_options['wpnukepig_posts_status'] == 'future') echo "selected"; ?> >scheduled</OPTION>
                                <OPTION value="pending" <?php if($my_options['wpnukepig_posts_status'] == 'pending') echo "selected"; ?> >pending review</OPTION>
                                <OPTION value="draft" <?php if($my_options['wpnukepig_posts_status'] == 'draft') echo "selected"; ?> >draft</OPTION>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['posts'])) {
                                  foreach($nuked_stats['posts'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Revisions:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_revisions" id="wpnukepig_revisions" value="yes" <?php if($my_options['wpnukepig_revisions'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['revisions'])) {
                                  foreach($nuked_stats['revisions'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            <SPAN style="margin-left:24px;">Nuke Just Page Revisions:</SPAN>
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_pages_revisions" id="wpnukepig_pages_revisions" value="yes" <?php if($my_options['wpnukepig_pages_revisions'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['pages_revisions'])) {
                                  foreach($nuked_stats['pages_revisions'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            <SPAN style="margin-left:24px;">Nuke Just Post Revisions:</SPAN>
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_posts_revisions" id="wpnukepig_posts_revisions" value="yes" <?php if($my_options['wpnukepig_posts_revisions'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['posts_revisions'])) {
                                  foreach($nuked_stats['posts_revisions'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Custom Fields:</SPAN>
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_custom_fields" id="wpnukepig_custom_fields" value="yes" <?php if($my_options['wpnukepig_custom_fields'] == 'yes') echo "checked"; ?> >
                              named:
                              <SELECT  name="wpnukepig_custom_fields_name" id="wpnukepig_custom_fields_name">
                                <OPTION value="all" <?php if($my_options['wpnukepig_custom_fields_name'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <?php
                                  $sql_select = "SELECT DISTINCT m.meta_key FROM {$wpdb->prefix}postmeta m LEFT JOIN {$wpdb->prefix}posts p ON p.ID = m.post_id WHERE m.meta_key <> '' AND m.meta_key NOT LIKE '\\_%' AND p.post_type = 'post' ";
                                  $rows = $wpdb->get_results($sql_select);
                                  if($rows) {
                                    foreach($rows as $row) {
                                      echo "<OPTION value='{$row->meta_key}' " . ($my_options['wpnukepig_custom_fields_name'] == $row->meta_key ? "selected" : "") . ">{$row->meta_key}</OPTION>\n";
                                    }
                                  }
                                ?>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['custom_fields'])) {
                                  foreach($nuked_stats['custom_fields'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            <SPAN style="margin-left:24px;">Nuke Just Page Custom Fields:</SPAN>
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_pages_custom_fields" id="wpnukepig_pages_custom_fields" value="yes" <?php if($my_options['wpnukepig_pages_custom_fields'] == 'yes') echo "checked"; ?> >
                              named:
                              <SELECT  name="wpnukepig_pages_custom_fields_name" id="wpnukepig_pages_custom_fields_name">
                                <OPTION value="all" <?php if($my_options['wpnukepig_pages_custom_fields_name'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <?php
                                  $sql_select = "SELECT DISTINCT m.meta_key FROM {$wpdb->prefix}postmeta m LEFT JOIN {$wpdb->prefix}posts p ON p.ID = m.post_id WHERE m.meta_key <> '' AND m.meta_key NOT LIKE '\\_%' AND p.post_type = 'page' ";
                                  $rows = $wpdb->get_results($sql_select);
                                  if($rows) {
                                    foreach($rows as $row) {
                                      echo "<OPTION value='{$row->meta_key}' " . ($my_options['wpnukepig_pages_custom_fields_name'] == $row->meta_key ? "selected" : "") . ">{$row->meta_key}</OPTION>\n";
                                    }
                                  }
                                ?>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['pages_custom_fields'])) {
                                  foreach($nuked_stats['pages_custom_fields'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            <SPAN style="margin-left:24px;">Nuke Just Post Custom Fields:</SPAN>
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_posts_custom_fields" id="wpnukepig_posts_custom_fields" value="yes" <?php if($my_options['wpnukepig_posts_custom_fields'] == 'yes') echo "checked"; ?> >
                              named:
                              <SELECT  name="wpnukepig_posts_custom_fields_name" id="wpnukepig_posts_custom_fields_name">
                                <OPTION value="all" <?php if($my_options['wpnukepig_posts_custom_fields_name'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <?php
                                  $sql_select = "SELECT DISTINCT m.meta_key FROM {$wpdb->prefix}postmeta m LEFT JOIN {$wpdb->prefix}posts p ON p.ID = m.post_id WHERE m.meta_key <> '' AND m.meta_key NOT LIKE '\\_%' AND p.post_type = 'post' ";
                                  $rows = $wpdb->get_results($sql_select);
                                  if($rows) {
                                    foreach($rows as $row) {
                                      echo "<OPTION value='{$row->meta_key}' " . ($my_options['wpnukepig_posts_custom_fields_name'] == $row->meta_key ? "selected" : "") . ">{$row->meta_key}</OPTION>\n";
                                    }
                                  }
                                ?>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['posts_custom_fields'])) {
                                  foreach($nuked_stats['posts_custom_fields'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>

                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Tags:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_tags" id="wpnukepig_tags" value="yes" <?php if($my_options['wpnukepig_tags'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['tags'])) {
                                  foreach($nuked_stats['tags'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>
        
                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Categories:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_categories" id="wpnukepig_categories" value="yes" <?php if($my_options['wpnukepig_categories'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['categories'])) {
                                  foreach($nuked_stats['categories'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>
        
                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Comments:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_comments" id="wpnukepig_comments" value="yes" <?php if($my_options['wpnukepig_comments'] == 'yes') echo "checked"; ?> >
                              having status:
                              <SELECT  name="wpnukepig_comments_status" id="wpnukepig_comments_status">
                                <OPTION value="all" <?php if($my_options['wpnukepig_comments_status'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <OPTION value="1" <?php if($my_options['wpnukepig_comments_status'] == '1') echo "selected"; ?> >approved</OPTION>
                                <OPTION value="0" <?php if($my_options['wpnukepig_comments_status'] == '0') echo "selected"; ?> >pending</OPTION>
                                <OPTION value="spam" <?php if($my_options['wpnukepig_comments_status'] == 'spam') echo "selected"; ?> >spam</OPTION>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['comments'])) {
                                  foreach($nuked_stats['comments'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>
        
                        <TR valign="top">
                          <TD width="40%">
                            <SPAN style="margin-left:24px;">Nuke Just <a href="http://blogpig.com/products/commentpig" target="_blank">CommentPiG</a> Comments:</SPAN>
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_comments_commentpig" id="wpnukepig_comments_commentpig" value="yes" <?php if($my_options['wpnukepig_comments_commentpig'] == 'yes') echo "checked"; ?> >
                              having status:
                              <SELECT  name="wpnukepig_comments_commentpig_status" id="wpnukepig_comments_commentpig_status">
                                <OPTION value="all" <?php if($my_options['wpnukepig_comments_commentpig_status'] == 'all') echo "selected"; ?> >[ all ]</OPTION>
                                <OPTION value="1" <?php if($my_options['wpnukepig_comments_commentpig_status'] == '1') echo "selected"; ?> >approved</OPTION>
                                <OPTION value="0" <?php if($my_options['wpnukepig_comments_commentpig_status'] == '0') echo "selected"; ?> >pending</OPTION>
                                <OPTION value="spam" <?php if($my_options['wpnukepig_comments_commentpig_status'] == 'spam') echo "selected"; ?> >spam</OPTION>
                              </SELECT>
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['comments_commentpig'])) {
                                  foreach($nuked_stats['comments_commentpig'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>
        
                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Links:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_links" id="wpnukepig_links" value="yes" <?php if($my_options['wpnukepig_links'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['links'])) {
                                  foreach($nuked_stats['links'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>
        
                        <TR valign="top">
                          <TD width="40%">
                            Nuke All Link Categories:
                          </TD>
                          <TD width="60%">
                            <INPUT type="checkbox" name="wpnukepig_link_categories" id="wpnukepig_link_categories" value="yes" <?php if($my_options['wpnukepig_link_categories'] == 'yes') echo "checked"; ?> >
                            <P style="font-size:80%; margin-top:0px;">
                              <?php
                                if(isset($nuked_stats['link_categories'])) {
                                  foreach($nuked_stats['link_categories'] as $param => $value) {
                                    echo "$param: <STRONG>$value</STRONG> <BR />\n";
                                  }
                                }
                              ?>
                            </P>
                          </TD>
                        </TR>
        
                      </TABLE>
                    </p>
                    
                  </div>
                </div>
                <script type="text/javascript">
                //<![CDATA[
                  function are_you_completely_sure() {
                    if(confirm('Have you performed a backup of your WordPress blog?')) {
                      if(confirm('It is extremely important that you backup your WordPress blog before nuking data. \n\nWe cannot accept any responsibily for loss of data. \n\nHave you performed a backup of your WordPress blog?')) {
                        return true;
                      }
                      else {
                        alert('Please backup your WordPress blog before nuking any data. \n\nNukePiG will not continue. \n\nPlease run NukePiG again after you have performed a backup.');
                        return false;
                      }
                    }
                    else {
                      alert('Please backup your WordPress blog before nuking any data. \n\nNukePiG will not continue. \n\nPlease run NukePiG again after you have performed a backup.');
                      return false;
                    }
                  }
                //]]>
                </script>
                
                <input type="submit" class="button-primary" name="wpnukepig_submit" value="Nuke Selected Content &raquo;" onclick="return are_you_completely_sure();" />
                <BR /><BR />
                
              </div> <!--- class='meta-box-sortables' --->
              
            </div> <!--- class="has-sidebar-content" --->
            
          </div> <!--- class="has-sidebar" --->
          
        </div> <!--- class="metabox-holder" --->
  
      </form>
  
  <?php
  }
?>
