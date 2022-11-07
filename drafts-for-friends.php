<?php
/**
 * Drafts For Friends
 * 
 * Allow drafts preview without adding users as friends!
 * This will also work for scheduled and pending posts.
 * 
 * PHP version 8
 * 
 * Plugin Name: Drafts for Friends
 * Description: Allow drafts preview without adding users as friends!
 * 
 * @category Posts
 * @package  AutomatticCodeTest
 * @author   Neville Longbottom <neville.longbottom@codetest.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.txt GNU/GPLv3
 * @version  SVN: 2.2
 * @link     http://automattic.com/
 */
/**
 * Class for the preview of the drafts without adding users as friends!
 * 
 * @category Posts
 * @package  AutomatticCodeTest
 * @author   Neville Longbottom <neville.longbottom@codetest.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.txt GNU/GPLv3
 * @link     http://automattic.com/
 */
class DraftsForFriends
{
    /**
     * __construct 
     *
     * @return void
     */
    function __construct()
    {
        add_action('init', array(&$this, 'init'));
    }
    
    /**
     * Initial hooks for the plugin methods
     *
     * @return void
     */
    function init()
    {
        global $current_user;
        add_action('admin_menu', array($this, 'addAdminPages'));
        add_filter('the_posts', array($this, 'thePostsIntercept'));
        add_filter('posts_results', array($this, 'postsResultsIntercept'));

        $this->admin_options = $this->getAdminOptions();

        $this->user_options = ($current_user->ID > 0 &&
            isset($this->admin_options[$current_user->ID]))?
            $this->admin_options[$current_user->ID] : array();

        $this->saveAdminOptions();

        $this->adminPageInit();
    }

    /**
     * Enqueues the css and js to the plugin
     *
     * @return void
     */
    function adminPageInit()
    {
        wp_enqueue_script('jquery');
        add_action('admin_head', array($this, 'printAdminCss'));
        add_action('admin_head', array($this, 'printAdminJs'));
    }

    /**
     * Gets all existing admin options
     *
     * @return array
     */
    function getAdminOptions()
    {
        $saved_options = get_option('shared');
        return is_array($saved_options)? $saved_options : array();
    }

    /**
     * Updates the admin options
     * 
     * @return void
     */
    function saveAdminOptions()
    {
        global $current_user;
        if ($current_user->ID > 0) {
            $this->admin_options[$current_user->ID] = $this->user_options;
        }
        update_option('shared', $this->admin_options);
    }
    
    /**
     * Hooks the plugin submenu to the posts menu
     *
     * @return void
     */
    function addAdminPages()
    {
        add_submenu_page(
            "edit.php", __('Drafts for Friends', 'draftsforfriends'),
            __('Drafts for Friends', 'draftsforfriends'), 1,
            'drafts-for-friends.php', array($this, 'outputExistingMenuSubAdminPage')
        );
    }
    
    /**
     * Calculates the remaining expire time
     *
     * @param array $params array with the expire time and measure specified
     * 
     * @return int
     */
    function calc($params)
    {
        $exp = 60;
        $multiply = 60;
        if (isset($params['expires']) && ($e = intval($params['expires']))) {
            $exp = $e;
        }
        $mults = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 24*3600);
        if ($params['measure'] && $mults[$params['measure']]) {
            $multiply = $mults[$params['measure']];
        }
        return $exp * $multiply;
    }
    
    /**
     * It validates if the intended post to share has a valid ID,
     * and if the status is published.
     * If neither applies, it will add the remaining time,
     * and will generate a unique key to store with the user permission.
     *
     * @param array $params 
     * 
     * @return void
     */
    function processPostOptions($params)
    {
        global $current_user;
        if ($params['post_id']) {
            $p = get_post($params['post_id']);
            if (!$p) {
                return __('There is no such post!', 'draftsforfriends');
            }
            if ('publish' == get_post_status($p)) {
                return __('The post is published!', 'draftsforfriends');
            }
            $this->user_options['shared'][] = array('id' => $p->ID,
                'expires' => $this->calc($params),
                'key' => 'baba_' . wp_generate_password(8, $special_chars = false) );
            $this->saveAdminOptions();
        }    
    }
    
    /**
     * Deletes the shared post
     *
     * @param mixed $params post that will no longer be shared
     * 
     * @return void
     */
    function processDelete($params)
    {
        $shared = array();
        foreach ($this->user_options['shared'] as $share) {
            if ($share['key'] == $params['key']) {
                continue;
            }
            $shared[] = $share;         
        }
        $this->user_options['shared'] = $shared;
        $this->saveAdminOptions();
    }

     /**
      * Extends the shared post
      *
      * @param mixed $params post that will be shared for a longer period
      * 
      * @return void
      */
    function processExtend($params)
    {
        $shared = array();
        foreach ($this->user_options['shared'] as $share) {
            if ($share['key'] == $params['key']) {
                $share['expires'] += $this->calc($params);
            }
            $shared[] = $share;
        }
        $this->user_options['shared'] = $shared;
        $this->saveAdminOptions();
    }
    
    /**
     * Gets all posts with post_status equal to draft, pending or future
     *
     * @return array
     */
    function getDrafts()
    {
        global $current_user;
        $args = array(
            'post_author' => $current_user->ID,
            'post_status' => 'draft'
        );
        $my_drafts = get_posts($args);

        $args = array(
            'post_author' => $current_user->ID,
            'post_status' => 'future'
        );
        $my_scheduled = get_posts($args);

        $args = array(
            'post_author' => $current_user->ID,
            'post_status' => 'pending'
        );
        $my_pending = get_posts($args);

        $ds = array(
            array(
                __('Your Drafts:', 'draftsforfriends'),
                count($my_drafts), $my_drafts,
            ),
            array(
                __('Your Scheduled Posts:', 'draftsforfriends'),
                count($my_scheduled), $my_scheduled,
            ),
            array(
                __('Pending Review:', 'draftsforfriends'),
                count($my_pending), $my_pending,
            ),
        );
        return $ds; 
    }

    /**
     * Get all shared posts
     * 
     * @return array
     **/    
    function getShared()
    {
            return $this->user_options['shared'];
        
    }
    
    /**
     * Returns the expire time in readable format
     *
     * @param mixed $expire time left for the post to be seen
     * 
     * @return int
     */
    function readableExpireTime($expire)
    {
        if ($expire>24*3600) {
            return trim(floor($expire/86400)).' day(s)';
        } elseif ($expire>3600) {
            return trim(floor($expire/3600)).' hours(s)';
        } elseif ($expire>60) {
            return trim(floor($expire/60)).' minutes(s)';
        } else {
            return trim($expire).' second(s)';    
        }
    }
    
    /**
     * Sub menu admin html page with js, css and php methods 
     *
     * @return void
     */
    function outputExistingMenuSubAdminPage()
    {
        if (( isset($_POST['dff_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['dff_nonce']), 'submit_nonce' )) ||
            ( isset($_POST['dff_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['dff_nonce']), 'extend_nonce' )) ||
            ( isset( $_GET['dff_nonce']) && wp_verify_nonce(sanitize_text_field( $_GET['dff_nonce']), 'delete_nonce' )) )
        {
            
            if (isset($_POST['dff_submit']) ) {
                $t = $this->processPostOptions($_POST);

            } elseif (isset($_POST['dff_extend'])) {
                $t = $this->processExtend($_POST);

            } elseif (isset($_GET['action']) && $_GET['action'] == 'delete') {
                $t = $this->processDelete($_GET);
            }
        }
        $ds = $this->getDrafts();
        ?>
        <div class="wrap">
            <h2><?php _e('Drafts for Friends', 'draftsforfriends'); ?></h2>
            <?php
            if (isset($t)) {
                if ($t) {
                    ?>
                    <div id="message" class="updated fade"><?php echo esc_html($t); ?></div>
                    <?php
                }
            }
            ?>
            <h3><?php _e('Currently shared drafts', 'draftsforfriends'); ?></h3>
            <table class="widefat">
                <thead>
                <tr>
                    <th><?php _e('ID', 'draftsforfriends'); ?></th>
                    <th><?php _e('Title', 'draftsforfriends'); ?></th>
                    <th><?php _e('Link', 'draftsforfriends'); ?></th>
                    <th colspan="2" class="actions">
                        <?php _e('Actions', 'draftsforfriends');?>
                    </th>
                    <th><?php _e('Expired After', 'draftsforfriends'); ?></th>
                </tr>
                </thead>
                <tbody>
                    <?php
                    $s = $this->getShared();
                    if (isset($s)) {
                        foreach ($s as $share):
                            $p = get_post($share['id']);
                            $url = get_bloginfo('url') . '/?p=' . $p->ID . '&draftsforfriends='. $share['key']; ?>
                            <tr>
                                <td><?php echo esc_html($p->ID); ?></td>
                                <td><?php echo esc_html($p->post_title); ?></td>
                                <td><a href="<?php echo esc_url($url); ?>">
                                    <?php echo esc_url($url); ?>
                                </a></td>
                                <td class="actions">
                                    <form class="draftsforfriends-extend" id="draftsforfriends-extend-form-<?php echo esc_html($share['key']);?>" action="" method="post">
                                        <input type="hidden" name="key" value="<?php echo esc_attr($share['key']); ?>" />
                                        <?php wp_nonce_field( 'extend_nonce' , 'dff_nonce' ); ?>
                                        <input type="submit" class="button" name="dff_extend" value="<?php _e('extend', 'draftsforfriends');?>"/>
                                        <?php _e('by', 'draftsforfriends');?>
                                        <input name="expires" type="text" value="2" size="4"/>
                                        <select name="measure">
                                            <option value="s"><?php                     __('seconds', 'draftsforfriends')?></option>
                                            <option value="m"><?php                     __('minutes', 'draftsforfriends')?></option>
                                            <option value="h" selected="selected"><?php __('hours', 'draftsforfriends')?></option>
                                            <option value="d"><?php                     __('days', 'draftsforfriends')?></option>
                                        </select>

                                        <a class="draftsforfriends-extend-cancel" href="javascript:draftsforfriends.cancel_extend('<?php echo esc_html($share['key']); ?>');">
                                            <?php _e('Cancel', 'draftsforfriends');?>
                                        </a>
                                    </form>
                                </td>
                                <td class="actions">
                                    <?php 
                                        $url = "edit.php?page=drafts-for-friends.php&action=delete&key=".esc_attr($share['key']);
                                    ?>
                                    
                                    <a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url($url), 'delete_nonce', 'dff_nonce')); ?>"><?php _e('delete', 'draftsforfriends'); ?></a>
                                </td>
                                <td>
                                    <?php
                                    if (isset($share['expires'])) {
                                        echo esc_html($this->readableExpireTime($share['expires']));
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    } else {
                        ?>
                        <tr>
                            <td colspan="5">
                                <?php _e('No shared drafts!', 'draftsforfriends');?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <h3><?php _e('Drafts for Friends', 'draftsforfriends'); ?></h3>
            <form id="draftsforfriends-share" action="" method="post">
                <p>
                    <select id="draftsforfriends-postid" name="post_id">
                    <option value="">
                        <?php _e('Choose a draft', 'draftsforfriends'); ?>
                    </option>
                    <?php
                    foreach ($ds as $dt):
                        if ($dt[1]) {
                            ?>
                            <option value="" disabled="disabled"></option>
                            <option value="" disabled="disabled">
                                <?php echo esc_html($dt[0]); ?>
                            </option>
                            <?php
                            foreach ($dt[2] as $d):
                                if (empty($d->post_title)) {
                                    continue;
                                }
                                ?>
                                <option value="<?php echo esc_attr($d->ID)?>">
                                    <?php echo esc_html($d->post_title);?>
                                </option>
                                <?php
                            endforeach;
                        }
                    endforeach;
                    ?>
                    </select>
                </p>
                <p>
                    <input type="submit" class="button" name="dff_submit" value="<?php _e('Share it', 'draftsforfriends'); ?>" />
                    <?php wp_nonce_field( 'submit_nonce', 'dff_nonce' ); ?>
                    <?php _e('for', 'draftsforfriends'); ?>
                    <input name="expires" type="text" value="2" size="4"/>
                    <select name="measure">
                        <option value="s"><?php                     __('seconds', 'draftsforfriends')?></option>
                        <option value="m"><?php                     __('minutes', 'draftsforfriends')?></option>
                        <option value="h" selected="selected"><?php __('hours', 'draftsforfriends')?></option>
                        <option value="d"><?php                     __('days', 'draftsforfriends')?></option>
                    </select>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * ---
     *
     * @param mixed $pid pid of the user seeing the page
     * 
     * @return bool
     */
    function canView($pid)
    {
        foreach ($this->admin_options as $option) {
            $shares = $option['shared'];
            if (isset($shares)) {
                foreach ($shares as $share) {
                    if ((isset($_GET['draftsforfriends'])) && $share[ 'key'] == $_GET['draftsforfriends'] && $pid) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * https://developer.wordpress.org/reference/hooks/posts_results/
     *
     * @param mixed $pp existing posts
     * 
     * @return array
     */
    function postsResultsIntercept($pp)
    {
        if (1 != count($pp)) {
            return $pp;
        }
        $p = $pp[0];
        $status = get_post_status($p);
        if ('publish' != $status && $this->canView($p->ID)) {
            $this->shared_post = $p;
        }
        return $pp;
    }
    
    /**
     * https://developer.wordpress.org/reference/hooks/the_posts/
     *
     * @param mixed $pp posts
     * 
     * @return array
     */
    function thePostsIntercept($pp)
    {
        if (empty($pp) && !is_null($this->shared_post)) {
            return array($this->shared_post);
        } else {
            $this->shared_post = null;
            return $pp;
        }
    }

    /**
     * CSS for the plugin page
     *
     * @return void
     */
    function printAdminCss()
    {
        ?>
        <style type="text/css">
            a.draftsforfriends-extend, a.draftsforfriends-extend-cancel
            { display: none; }
            form.draftsforfriends-extend { white-space: nowrap; }
            form.draftsforfriends-extend, form.draftsforfriends-extend input,
            form.draftsforfriends-extend select { font-size: 11px; }
            th.actions, td.actions { text-align: center; }
        </style>
        <?php
    }

    /**
     * Javascript for the plugin page
     *
     * @return void
     */
    function printAdminJs()
    {
        ?>
        <script type="text/javascript">
        /*global jQuery*/
        jQuery(function() {
            jQuery('form.draftsforfriends-extend').hide();
            jQuery('a.draftsforfriends-extend').show();
            jQuery('a.draftsforfriends-extend-cancel').show();
            jQuery('a.draftsforfriends-extend-cancel').css('display', 'inline');
        });
        window.draftsforfriends = {
            toggle_extend: function(key) {
                jQuery('#draftsforfriends-extend-form-'+key).show();
                jQuery('#draftsforfriends-extend-link-'+key).hide();
                jQuery('#draftsforfriends-extend-form-'+key+'
                    input[name="expires"]').focus();
            },
            cancel_extend: function(key) {
                jQuery('#draftsforfriends-extend-form-'+key).hide();
                jQuery('#draftsforfriends-extend-link-'+key).show();
            }
        };
        </script>
        <?php
    }
}

new draftsforfriends();
