<?php


 if ( ! defined( 'ABSPATH' ) ) exit;
 
class bp_course_mails{

   var $settings;
   var $subject;
   var $user_email;
    public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new bp_course_mails();
        return self::$instance;
    }

    private function __construct(){

        if(class_exists('WPLMS_tips')){
            $wplms_settings = WPLMS_tips::init();
            $settings = $wplms_settings->lms_settings;
        }else{
            $settings = get_option('lms_settings');  
        }
      
        if(isset($settings) && isset($settings['activate'])){
            $this->activate = $settings['activate'];
        }

        if(isset($settings) && isset($settings['forgot'])){
            $this->forgot = $settings['forgot'];
        }
        if(isset($settings['email_settings']['enable_html_emails']) && ($settings['email_settings']['enable_html_emails'] == 'on' || $settings['email_settings']['enable_html_emails'] === 'on')){
            $this->html_emails = 1;  
        }else{
            $this->html_emails = 0; 
        }
      

        add_filter('bp_core_signup_send_validation_email_to',array($this,'user_mail'));

        add_filter('bp_core_signup_send_validation_email_subject',array($this,'bp_course_activation_mail_subject'));    
        add_filter('bp_core_signup_send_validation_email_message',array($this,'bp_course_activation_mail_message'),10,3);

        add_filter ( 'retrieve_password_title', array($this,'forgot_password_subject'), 10, 1 );
        add_filter ( 'retrieve_password_message', array($this,'forgot_password_message'), 10, 2 );

        add_filter('messages_notification_new_message_message',array($this,'bp_course_bp_mail_filter'),10,7);
        add_filter( 'wp_mail_content_type', array($this,'set_html_content_type' ));
        add_filter( 'wp_mail_from', array($this,'custom_wp_mail_from' ));
        add_filter( 'wp_mail_from_name', array($this,'custom_wp_mail_from_name' ));

        //DISABLE BuddyPress Emails
        //add_filter( 'bp_email_use_wp_mail', '__return_false');
        //ADD ACTIVATION HOOK
        
        add_action( 'admin_notices', array($this,'wplms_emails_migrate_notice' ));
        add_action('wp_ajax_wplms_emails_migrate',array($this,'wplms_emails_migrate'));
        add_action('wp_ajax_wplms_emails_dismiss_migrate',array($this,'wplms_emails_dismiss_migrate'));

        // Run on Installation
        add_action('wplms_after_sample_data_import',array($this,'wplms_emails_migrate'),9999);
    }

    function email_content_type($x){
        //If migrated return $x
        return 'text/html';
    }

    function enable_html(){
      return $this->html_emails;
    }

    function custom_wp_mail_from( $original_email_address ) {
      if(class_exists('WPLMS_tips')){
        $wplms_settings = WPLMS_tips::init();
        $settings = $wplms_settings->lms_settings;
      }else{
        $settings = get_option('lms_settings');  
      }

      if(isset($settings) && isset($settings['email_settings']) && !empty($settings['email_settings']['from_email'])){
        return $settings['email_settings']['from_email'];
      }
      return $original_email_address;
    }

    function custom_wp_mail_from_name( $original_email_from ) {
      if(class_exists('WPLMS_tips')){
        $wplms_settings = WPLMS_tips::init();
        $settings = $wplms_settings->lms_settings;
      }else{
        $settings = get_option('lms_settings');  
      }

      if(isset($settings) && isset($settings['email_settings']) && !empty($settings['email_settings']['from_name'])){
        return $settings['email_settings']['from_name'];
      }

      return $original_email_from;
    }
    
    function bp_course_bp_mail_filter($email_content, $sender_name, $subject, $content, $message_link, $settings_link, $ud){
       $settings = get_option('lms_settings');
      if(!empty($this->html_emails)){
        $email_content = bp_course_process_mail(bp_core_get_user_displayname($ud->ID),$subject,$email_content); 
      }

      return $email_content;
    }
    
    function set_html_content_type($type) {
      if(!empty($this->html_emails))
        return 'text/html';

      return $type;
    }

   function user_mail($email){
      $this->activate_user_email = $email;
      return $email;
   }

   function bp_course_activation_mail_subject($subject){
    $this->activate_subject = $subject;

    if(isset($this->activate) && is_array($this->activate) && isset($this->activate['subject'])){
      $this->activate_subject = $this->activate['subject'];
    }
    return $subject;
  }
  
  function bp_course_activation_mail_message($message,$user_id,$link){

    if(isset($this->activate) && is_array($this->activate) && isset($this->activate['message'])){
      $message = $this->activate['message'];
      if(strpos($message,'{{activationlink}}') === false){
        $message .= $message.' '.sprintf(__('Click %s to Activate account.','vibe'),'<a href="'.$link.'">'.__('this link','vibe').'</a>'); 
      }else{
        $message = str_replace('{{activationlink}}',$link,$message);
      }
      if(!empty($this->html_emails))
        $message = bp_course_process_mail($this->activate_user_email,$this->activate_subject,$message);
    }    

    return $message;
  }

  function forgot_password_subject($subject){

    if(isset($this->forgot) && is_array($this->forgot) && !empty($this->forgot['subject'])){
      $subject = $this->forgot['subject'];
    }
    return $subject;
  }

  function forgot_password_message($old_message, $key){

    if(isset($this->forgot) && is_array($this->forgot) && !empty($this->forgot['message'])){
      $message = $this->forgot['message'];
    }else{
      $message = $old_message;
    }

    if ( strpos( $_POST['user_login'], '@' ) ){
        $user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
    }else{
        $login = trim($_POST['user_login']);
        $user_data = get_user_by('login', $login);
    }

    $user_login = $user_data->user_login;

    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

    $message = str_replace("{{forgotlink}}",$reset_url,(str_replace("{{username}}",$user_login,$message))); //. "\r\n";

    if(!empty($this->html_emails)){
      $message = bp_course_process_mail($user_data->user_email,$this->forgot['subject'],$message);
    }
        
    return $message;
  }

  // BuddyPRess EMAIL MIGRATION

    //MIGRATE EMAILS NOTICE
    function emails_migrated(){

        if(function_exists('vibe_get_option')){
            $take_course_page = vibe_get_option('take_course_page') ;
            if(empty($take_course_page)){
                return true;
            }
        }
        if(!function_exists('bp_get_email_post_type')){
          return false;
        }
        if(isset($this->migration_status)){
            return $this->migration_status;
        }else{
            $migrated = get_option('wplms_bp_emails');
            if($migrated == bp_course_version()){
                $this->migration_status = true;
                return $this->migration_status;
            }
        }

        $count = 0;
        $emails = bp_course_all_mails();
        $tax = bp_get_email_tax_type();
        $terms = array_keys($emails);
        if(!term_exists($terms[count($terms)-1],$tax)){
            $count++;
        }

        $flag = 0;
        if(!term_exists($terms[count($terms)-1],$tax)){ // Check if the last term in the mails has been migrated
          $flag=1;
        } 
        
        if(empty($flag)){
           $this->migration_status = true; // Show notice
        }else{
          $this->migration_status = false;  // Do not show notice
        }
        
        return $this->migration_status;
    }

    function wplms_emails_migrate_notice(){
      $x = $this->emails_migrated(); // for php 5.4 and below
        if(empty($x)){
            $count=0;
            //Count number of emails to be migrated
            $emails = bp_course_all_mails();
            $tax = bp_get_email_tax_type();
            $terms = array_keys($emails);
            foreach($terms as $term){
              if(!term_exists($term,$tax)){
                $count++;
              }  
            }
            $class = 'notice notice-error is-dismissible';
            $nonce = wp_create_nonce('wplms_emails_migrate_notice');
            $message = sprintf(__( '%sMigrate WPLMS email templates to BuddyPress Emails.%s  %s mail templates will be migrated. Refer %s more information & tutorial%s   %s Migrate all email templates to BuddyPress %s %s No, Thanks, I like  different email templates. %s', 'vibe' ),'<strong>','</strong>',$count,'<a href="http://vibethemes.com/documentation/wplms/knowledge-base/wplms-email-migration-to-buddypress-emails" target="_blank">','</a>','<br><br><a id="wplms_emails_migrate" class="button-primary" data-nonce="'.$nonce.'">','</a>','<a id="wplms_dismiss_emails_migrate" class="button" data-nonce="'.$nonce.'">','</a><div class="migrate_progress"><span></span></div>');

            printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
            ?>
            <style>.migrate_progress{display:none;width:100%;overflow:hidden;background:#fafafa;border:1px solid rgba(0,0,0,0.1);border-radius:2px;}.migrate_progress span{width:0%;display:block;padding:3px;background:#46b450;-webkit-transition: width 0.5s ease-in-out;
    -moz-transition: width 0.5s ease-in-out;-o-transition: width 0.5s ease-in-out;transition: width 0.5s ease-in-out;}</style>
            <script>
            jQuery(document).ready(function($){
                $('#wplms_emails_migrate').on('click',function(){
                    var $this=$(this);
                    
                    if($this.hasClass('disabled'))
                        return;

                    $this.addClass('disabled');
                    $this.parent().find('.button').hide(100);
                    $('.migrate_progress').show(100);
                    setTimeout(function(){$('.migrate_progress span').css('width','40%');},500);
                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        data: { action: 'wplms_emails_migrate', 
                                security:$this.attr('data-nonce'),
                            },
                        cache: false,
                        success: function (html) {
                            $('.migrate_progress span').css('width','80%');
                            setTimeout(function(){$('.migrate_progress span').css('width','100%');
                                $this.closest('.notice-error').removeClass('notice-error').addClass('notice-success');},500);
                            $this.show(100).html(html).attr('id','wplms_emails_migrated');
                            setTimeout(function(){$this.closest('.notice').fadeOut(1500);},500);
                        }
                    });
                });
                $('#wplms_dismiss_emails_migrate').on('click',function(){
                    var $this=$(this);
                    $this.closest('.notice').css('opacity','0.4');
                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        data: { action: 'wplms_emails_dismiss_migrate', 
                                security:$this.attr('data-nonce'),
                            },
                        cache: false,
                        success: function (html) {
                            $this.closest('.notice').fadeOut(1500);
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    function wplms_emails_migrate(){

        $emails = bp_course_all_mails();
        $post_type = bp_get_email_post_type();
        $tax_type = bp_get_email_tax_type();
        foreach($emails as $id=>$email){
            
            if(!term_exists($id,$tax_type)){
              $id = wp_insert_term($id,$tax_type, array('description'=> $email['description']));
              if(!is_wp_error($id)){
                  $textbased = str_replace('titlelink','name',$email['message']);
                  $textbased = str_replace('userlink','name',$email['message']);
                  $post_id = wp_insert_post(array(
                              'post_title'=> '[{{{site.name}}}] '.$email['subject'],
                              'post_content'=> $email['message'],
                              'post_excerpt'=> $textbased,
                              'post_type'=> $post_type,
                              'post_status'=> 'publish',
                          ),true);

                  wp_set_object_terms( $post_id, $id, $tax_type );
              }
            }
        }

        update_option('wplms_bp_emails',bp_course_version());

        if(defined('DOING_AJAX') && isset($_POST['security']) && isset($_POST['action']) && $_POST['action'] == 'wplms_emails_migrate'){
          _ex('Migration complete.','Migrate WPLMS emails to BuddyPress success message','vibe');
          die();
        }
    }

    function wplms_emails_dismiss_migrate(){
        update_option('wplms_bp_emails',bp_course_version()); // Dismissed
        die();
    }

}

bp_course_mails::init();



// BP Course Mail function

function bp_course_wp_mail($to,$subject,$message,$args=''){

    /*=== Migartion to BuddyPRess HTML emails ==*/
    $mails = bp_course_mails::init();
    $x = $mails->enable_html(); // for php 5.4 and below
    $y = $mails->emails_migrated(); // for php 5.4 and below
    if( $y && !empty($args['tokens']) && empty($x)){
     
        $email_type = $args['action'];
        $bpargs = array(
            'tokens' => $args['tokens'],
        );
        bp_send_email( $email_type,$to, $bpargs );
    return;
  }

  if(empty($to))
    return;
      
  if(!is_array($to)){
    $to = array($to);
  }
  
    $headers = "MIME-Version: 1.0" . "\r\n";

    if(class_exists('WPLMS_tips')){
      $wplms_settings = WPLMS_tips::init();
      $settings = $wplms_settings->lms_settings;
    }else{
      $settings = get_option('lms_settings');  
    }
     
    if(isset($settings['email_settings']) && is_array($settings['email_settings'])){
        if(isset($settings['email_settings']['from_name'])){
          $name = $settings['email_settings']['from_name'];
        }else{
          $name =get_bloginfo('name');
        }
        if(isset($settings['email_settings']['from_email'])){
          $email = $settings['email_settings']['from_email'];
        }else{
          $email = get_option('admin_email');
        }
        if(isset($settings['email_settings']['charset'])){
          $charset = $settings['email_settings']['charset'];
        }else{
           $charset = 'utf8'; 
        }
    }
    $headers .= "From: $name<$email>". "\r\n";
   
    if($mails->enable_html())
      $headers .= "Content-type: text/html; charset=$charset" . "\r\n";
    
    $flag = apply_filters('bp_course_disable_html_emails',1);
    
    if($flag){
      if($mails->enable_html()){
        if(is_array($to)){
          $subject=html_entity_decode($subject);
          $message = bp_course_process_mail($to,$subject,$message,$args);
          $message = apply_filters('wplms_email_templates',$message,$to,$subject,$message,$args);
          foreach($to as $t){
            $message = str_replace('{{name}}',$t,$message);  
            if(!empty($message))
              wp_mail($t,$subject,$message,$headers);    
          }
        }
        
      }
    }else{
      $message = apply_filters('wplms_email_templates',$message,$to,$subject,$message,$args);
      if(!empty($message))
        wp_mail($to,$subject,$message,$headers);
    }
}

// BP Course Mail function to be extended in future

function bp_course_process_mail($to,$subject,$message,$args=''){


  

    $template = html_entity_decode(get_option('wplms_email_template'));
    if(!isset($template) || !$template || strlen($template) < 5)
      return $message;
     

    $site_title = get_option('blogname');
    $site_description = get_option('blogdescription');
    $logo_url = vibe_get_option('logo');
    $logo = '<a href="'.get_option('home_url').'"><img src="'.$logo_url.'" alt="'.$site_title.'" style="max-width:50%;"/></a>';

    $sub_title = $subject; 

    if(is_array($to)){
      $name .= implode($to);
    }else{
      $name = $to;  
    }
    
    if(!is_array($to)){
      $user = get_user_by('email',$to);
      $name = bp_core_get_userlink($user->id);
      if(empty($name))
        $name = $user->first_name;
    }

    $datetime = date_i18n( get_option( 'date_format' ), time());
    if(isset($args['item_id'])){
      $instructor_id = get_post_field('post_author', $args['item_id']);
      $sender = bp_core_get_user_displayname($instructor_id);
      $instructing_courses=apply_filters('wplms_instructing_courses_endpoint','instructing-courses');
      $sender_links = apply_filters('wplms_emails_sender_links','<a href="'.bp_core_get_user_domain( $instructor_id ).'">'.__('Profile','vibe-customtypes').'</a>&nbsp;|&nbsp;<a href="'.get_author_posts_url($instructor_id).$instructing_courses.'/">'.__('Courses','vibe-customtypes').'</a>');
      $item = get_the_title($args['item_id']);
      $item_links  = apply_filters('wplms_emails_item_links','<a href="'.get_permalink( $args['item_id'] ).'">'.__('Link','vibe-customtypes').'</a>&nbsp;|&nbsp;<a href="'.bp_core_get_user_domain($instructor_id).'/">'.__('Instructor','vibe-customtypes').'</a>');
      $unsubscribe_link = bp_core_get_user_domain($user_id).'/settings/notifications';
    }else{
      $sender ='';
      $sender_links ='';
      $item ='';
      $item_links ='';
      $unsubscribe_link = '#';
      $template = str_replace('cellpadding="28"','cellpadding="0"',$template);
    }
   
    $copyright = vibe_get_option('copyright');
    $link_id = vibe_get_option('email_page');
    if(is_numeric($link_id)){
      $array = array(
        'to' => $to,
        'subject'=>$subject,
        'message'=>$message,
        'args'=>$args
        );
      $link = get_permalink($link_id).'?vars='.urlencode(json_encode($array));
    }else{
      $link = '#';
    }


    $template = str_replace('{{logo}}',$logo,$template);
    $template = str_replace('{{subject}}',$subject,$template);
    $template = str_replace('{{sub-title}}',$sub_title,$template);
    $template = str_replace('{{name}}',$name,$template);
    $template = str_replace('{{datetime}}',$datetime,$template);
    $template = str_replace('{{message}}',$message,$template);
    $template = str_replace('{{sender}}',$sender,$template);
    $template = str_replace('{{sender_links}}',$sender_links,$template);
    $template = str_replace('{{item}}',$item,$template);
    $template = str_replace('{{item_links}}',$item_links,$template);
    $template = str_replace('{{site_title}}',$site_title,$template);
    $template = str_replace('{{site_description}}',$site_description,$template);
    $template = str_replace('{{copyright}}',$copyright,$template);
    $template = str_replace('{{unsubscribe_link}}',$unsubscribe_link,$template);
    $template = str_replace('{{link}}',$link,$template);
    $template = bp_course_minify_output($template);
    return $template;
}

function bp_course_minify_output($buffer){
  $search = array(
  '/\>[^\S ]+/s',
  '/[^\S ]+\</s',
  '/(\s)+/s'
  );
  $replace = array(
  '>',
  '<',
  '\\1'
  );
  if (preg_match("/\<html/i",$buffer) == 1 && preg_match("/\<\/html\>/i",$buffer) == 1) {
    $buffer = preg_replace($search, $replace, $buffer);
  }
  return $buffer;
}

function send_html( $message,    $user_id, $activate_url ) {
  if(bp_course_mails::enable_html())
    $message = bp_course_process_mail($to,$subject,$message,$args); 

  return $message;
}

/*=== BUDDYPRESS EMAILS ===*/






function bp_course_email_tokens($args){
    switch($case){
        case 'course.name':
        return get_the_title($item_id);
        break;
        case 'course.titlelink':
          return '<a href="'.get_permalink($item_id).'">'.get_the_title($item_id).'</a>';
        break;
        case 'student.userlink':
          return bp_core_get_userlink($user_id);
        break;
        case 'student.name':
          return bp_core_get_user_displayname($user_id);
        break;
        case 'course.code':
          return $code;
        break;
        case 'unit.title':
          return '<a href="'.get_permalink($secondary_item_id).'">'.get_the_title($secondary_item_id).'</a>';
        break;
        case 'unit.titlelink':
          return '<a href="'.get_permalink($secondary_item_id).'">'.get_the_title($secondary_item_id).'</a>';
        break;
        case 'course.instructorlink':
          return bp_core_get_userlink($instructor_id);
        break;
    }
}

function bp_course_all_mails(){
    $bp_course_mails = array(
        'student_course_announcement'=>array(
            'description'=> __('Student : Announcement in Course','vibe'),
            'subject' =>  sprintf(__('Announcement for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.announcement}}'
        ),
        'instructor_course_announcement'=>array(
            'description'=> __('Instructor : Announcement in Course','vibe'),
            'subject' =>  sprintf(__('Announcement for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.announcement}}'
        ),
        'student_course_news'=>array(
            'description'=> __('Student : News in Course','vibe'),
            'subject' =>  sprintf(__('News for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.news}}'
        ),
        'instructor_course_news'=>array(
            'description'=> __('Instructor : News in Course','vibe'),
            'subject' =>  sprintf(__('News for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.news}}',
        ),

        'student_course_subscribed'=>array(
            'description'=> __('Student : Student subscribes to course','vibe'),
            'subject' =>  sprintf(__('Subscribed for Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'re subscribed for course : %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_subscribed'=>array(
            'description'=> __('Instructor : Student subscribes to course','vibe'),
            'subject' =>  sprintf(__('Student subscribed for course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s subscribed for course : %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),

        'student_course_added'=>array(
            'description'=> __('Student : Student added to course','vibe'),
            'subject' =>  sprintf(__('Added to course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve been added to course : %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_added'=>array(
            'description'=> __('Instructor : Instructor adds Student to course','vibe'),
            'subject' =>  sprintf(__('Student added to course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('%s student added to course : %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}'),
        ),

        'student_course_start'=>array(
            'description'=> __('Student : Student started a course','vibe'),
            'subject' =>  sprintf(__('You started course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve started the course : %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_start'=>array(
            'description'=> __('Instructor : Student started a course','vibe'),
            'subject' =>  sprintf(__('Student started course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s started the course : %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),

        'student_course_submit'=>array(
            'description'=> __('Student : Student finishes a course','vibe'),
            'subject' =>  sprintf(__('Course %s submitted','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve submitted the course : %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_submit'=>array(
            'description'=> __('Instructor : Student finishes a course','vibe'),
            'subject' =>  sprintf(__('Student submitted course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s submitted the course : %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),

        'student_course_reset'=>array(
            'description'=> __('Student : Instructor resets course for a Student','vibe'),
            'subject' =>  sprintf(__('Course %s reset','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('%s Course was reset by Instructor','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_reset'=>array(
            'description'=> __('Instructor : Instructor resets course for a Student','vibe'),
            'subject' =>  sprintf(__('Course %s reset for Student','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Course %s was reset for student %s ','vibe'),'{{{course.titlelink}}}','{{{student.userlink}}}')
        ),

        'student_course_retake'=>array(
            'description'=> __('Student : Student retakes a course','vibe'),
            'subject' =>  sprintf(__('You retook the course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve retaken the Course %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_retake'=>array(
            'description'=> __('Instructor : Student retakes a course','vibe'),
            'subject' =>  sprintf(__('Course %s retaken by the Student','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Course %s was retaken by the student %s ','vibe'),'{{{course.titlelink}}}','{{{student.userlink}}}')
        ),

        'student_course_evaluation'=>array(
            'description'=> __('Student : Course evaluated for Student','vibe'),
            'subject' =>  sprintf(__('Course %s results available','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve obtained %s  in Course : %s','vibe'),'{{course.marks}}','{{{course.titlelink}}}')
        ),
        'instructor_course_evaluation'=>array(
            'description'=> __('Instructor : Course evaluated for Student','vibe'),
            'subject' =>  sprintf(__('Course %s results available','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s got %s out of 100 in course : %s','vibe'),'{{{student.userlink}}}','{{course.marks}}','{{{course.titlelink}}}')
        ),

        'student_course_badge'=>array(
            'description'=> __('Student : Student obtained course badge','vibe'),
            'subject' =>  sprintf(__('You got a Badge in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve obtained a Badge in Course : %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_badge'=>array(
            'description'=> __('Instructor : Student obtained course badge','vibe'),
            'subject' =>  sprintf(__('Student got a Badge in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s got a Badge in Course %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),

        'student_course_certificate'=>array(
            'description'=> __('Student : Student obtained course certificate','vibe'),
            'subject' =>  sprintf(__('You got a Certificate in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve obtained a certificate in Course : %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_certificate'=>array(
            'description'=> __('Instructor : Student obtained course certificate','vibe'),
            'subject' =>  sprintf(__('Student got a Certificate in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s got a Certificate in Course %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),

        'student_course_review'=>array(
            'description'=> __('Student : Student reviewed course','vibe'),
            'subject' =>  sprintf(__('You submitted a %s star review for Course %s','vibe'),'{{course.rating}}','{{course.name}}'),
            'message' =>  sprintf(__('You submitted a %s star review Course %s - %s','vibe'),'{{course.rating}}','{{{course.titlelink}}}','{{course.review}}')
        ),
        'instructor_course_review'=>array(
            'description'=> __('Instructor : Student reviewed course','vibe'),
            'subject' =>  sprintf(__('Student submitted a %s star review for Course %s','vibe'),'{{course.rating}}','{{course.name}}'),
            'message' =>  sprintf(__('Student %s submitted a %s star review for the Course %s - %s','vibe'),'{{{student.userlink}}}','{{course.rating}}','{{{course.titlelink}}}','{{course.review}}')
        ),

        'student_course_unsubscribe'=>array(
            'description'=> __('Student : Student unsubscribed from course','vibe'),
            'subject' =>  sprintf(__('You\'re unsubscribed from course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'re unsubscribed from the Course %s','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_course_unsubscribe'=>array(
            'description'=> __('Instructor : Student unsubscribed from course','vibe'),
            'subject' =>  sprintf(__('Student unsubscribed from Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s unsubscribed from Course %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),

        'student_course_codes'=>array(
            'description'=> __('Student : Student applied course code to course','vibe'),
            'subject' =>  sprintf(__('You applied course code in course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve applied the code %s on the course : %s','vibe'),'{{course.code}}','{{{course.titlelink}}}')
        ),
        'instructor_course_codes'=>array(
            'description'=> __('Instructor : Student applied course code to course','vibe'),
            'subject' =>  sprintf(__('Student applied code for Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s applied code %s for Course %s','vibe'),'{{{student.userlink}}}','{{course.code}}','{{{course.titlelink}}}')
        ),

        'student_unit_complete'=>array(
            'description'=> __('Student : Student completed a unit in course','vibe'),
            'subject' =>  sprintf(__('You completed unit %s in Course %s','vibe'),'{{unit.name}}','{{course.name}}'),
            'message' =>  sprintf(__('You completed a unit %s in Course %s','vibe'),'{{unit.name}}','{{{course.titlelink}}}')
        ),
        'instructor_unit_complete'=>array(
            'description'=> __('Instructor : Student completed a unit in course','vibe'),
            'subject' =>  sprintf(__('Student completed unit in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s completed unit %s in Course %s','vibe'),'{{{student.userlink}}}','{{unit.name}}','{{{course.titlelink}}}')
        ),

        'student_unit_instructor_complete'=>array(
            'description'=> __('Student : Instructor marked unit complete for Student in course','vibe'),
            'subject' =>  sprintf(__('Instructor marked unit complete in course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Unit %s was marked complete by Instructor %s in Course %s','vibe'),'{{unit.name}}','{{{course.instructorlink}}}','{{{course.titlelink}}}')
        ),
        'instructor_unit_instructor_complete'=>array(
            'description'=> __('Instructor : Instructor marked unit complete for Student in course','vibe'),
            'subject' =>  sprintf(__('Instructor marked unit %s comple for Student in Course %s','vibe'),'{{unit.name}}','{{course.name}}'),
            'message' =>  sprintf(__('Instructor %s completed the unit %s for Student %s in Course %s','vibe'),'{{instructor.userlink}}','{{unit.name}}','{{{student.userlink}}}','{{{course.titlelink}}}')
        ),
        'student_unit_instructor_uncomplete'=>array(
            'description'=> __('Student : Instructor marked unit incomplete for Student in course','vibe'),
            'subject' =>  sprintf(__('Instructor marked unit incomplete in course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Unit %s was marked incomplete by Instructor %s in Course %s','vibe'),'{{unit.name}}','{{{course.instructorlink}}}','{{{course.titlelink}}}')
        ),
        'instructor_unit_instructor_uncomplete'=>array(
            'description'=> __('Instructor : Instructor marked unit incomplete for Student in course','vibe'),
            'subject' =>  sprintf(__('Instructor marked unit %s incomplete for Student in Course %s','vibe'),'{{unit.name}}','{{course.name}}'),
            'message' =>  sprintf(__('Instructor %s marked the unit %s incomplete for Student %s in Course %s','vibe'),'{{instructor.userlink}}','{{unit.name}}','{{{student.userlink}}}','{{{course.titlelink}}}')
        ),
        'student_unit_comment'=>array(
            'description'=> __('Student : Student added a comment in unit in course','vibe'),
            'subject' =>  sprintf(__('You added a comment in unit %s','vibe'),'{{unit.name}}'),
            'message' =>  sprintf(__('Comment "%s" was added to unit %s','vibe'),'{{comment.comment_content}}','{{unit.name}}')
        ),
        'student_unit_comment_reply'=>array(
            'description'=> __('Student : Reply posted on your comment','vibe'),
            'subject' =>  sprintf(__('Reply posted on your comment in %s','vibe'),'{{unit.name}}'),
            'message' =>  sprintf(__('%s replied on your comment in Unit %s : %s ','vibe'),'{{{comment.userlink}}}','{{unit.name}}','{{comment.comment_content}}')
        ),
        'instructor_unit_comment'=>array(
            'description'=> __('Instructor : Student added a comment on Unit','vibe'),
            'subject' =>  sprintf(__('Student added a comment in unit %s','vibe'),'{{unit.name}}'),
            'message' =>  sprintf(__('Student %s added a comment "%s" in unit %s in course','vibe'),'{{{student.userlink}}}','{{comment.comment_content}}','{{unit.name}}')
        ),
        'student_start_quiz'=>array(
            'description'=> __('Student : You started the quiz','vibe'),
            'subject' =>  sprintf(__('You started the  quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('You started the quiz %s ','vibe'),'{{{quiz.titlelink}}}')
        ),
        'instructor_start_quiz'=>array(
            'description'=> __('Instructor : Student started a quiz','vibe'),
            'subject' =>  sprintf(__('Student started the quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('Student %s started the quiz %s','vibe'),'{{{student.userlink}}}','{{{quiz.titlelink}}}')
        ),
        'student_quiz_submit'=>array(
            'description'=> __('Student : Quiz submitted','vibe'),
            'subject' =>  sprintf(__('You submitted quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('You submitted quiz %s ','vibe'),'{{{quiz.titlelink}}}')
        ),
        'instructor_quiz_submit'=>array(
            'description'=> __('Instructor : Student submitted quiz','vibe'),
            'subject' =>  sprintf(__('Student submitted quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('Student %s submitted quiz %s','vibe'),'{{{student.userlink}}}','{{{quiz.titlelink}}}')
        ),
        'student_quiz_evaluation'=>array(
            'description'=> __('Student : Results available for quiz','vibe'),
            'subject' =>  sprintf(__('Results available for quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('You obtained %s out of %s in quiz %s ','vibe'),'{{quiz.marks}}','{{quiz.max}}','{{{quiz.titlelink}}}')
        ),
        'instructor_quiz_evaluation'=>array(
            'description'=> __('Instructor : Student results available for quiz','vibe'),
            'subject' =>  sprintf(__('Quiz %s evaluated for Student','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('Student %s got %s from %s in quiz %s','vibe'),'{{{student.userlink}}}','{{quiz.marks}}','{{quiz.max}}','{{{quiz.titlelink}}}')
        ),
        'student_quiz_retake'=>array(
            'description'=> __('Student : Quiz retake','vibe'),
            'subject' =>  sprintf(__('You retook the quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('You retook the quiz %s','vibe'),'{{{quiz.titlelink}}}')
        ),
        'instructor_quiz_retake'=>array(
            'description'=> __('Instructor : Student retook quiz','vibe'),
            'subject' =>  sprintf(__('Student retook the quiz %s','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('Student %s retook the quiz %s','vibe'),'{{{student.userlink}}}','{{{quiz.titlelink}}}')
        ),
        'student_quiz_reset'=>array(
            'description'=> __('Student : Quiz reset','vibe'),
            'subject' =>  sprintf(__('Quiz %s has been reset','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('Quiz %s was reset by Instructor','vibe'),'{{{quiz.titlelink}}}')
        ),
        'instructor_quiz_reset'=>array(
            'description'=> __('Instructor : Quiz reset for Student','vibe'),
            'subject' =>  sprintf(__('Quiz %s reset for Student','vibe'),'{{quiz.name}}'),
            'message' =>  sprintf(__('Quiz %s was reset for Student %s','vibe'),'{{{quiz.titlelink}}}','{{{student.userlink}}}')
        ),
        'student_start_assignment'=>array(
            'description'=> __('Student : Student started Assignment','vibe'),
            'subject' =>  sprintf(__('You started the assignment %s','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('You started the assignment %s','vibe'),'{{{assignment.titlelink}}}')
        ),
        'instructor_start_assignment'=>array(
            'description'=> __('Instructor : Student started an assignment','vibe'),
            'subject' =>  sprintf(__('Student started the assignment %s','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('Student %s started the assignment %s','vibe'),'{{{student.userlink}}}','{{{assignment.titlelink}}}')
        ),
        'student_assignment_submit'=>array(
            'description'=> __('Student : Results available for assignment','vibe'),
            'subject' =>  sprintf(__('You submitted the assignment %s','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('You submitted the assignment %s ','vibe'),'{{{assignment.titlelink}}}')
        ),
        'instructor_assignment_submit'=>array(
            'description'=> __('Instructor : Student submitted assignment','vibe'),
            'subject' =>  sprintf(__('Student submitted the assignment %s','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('Student %s submitted the assignment %s','vibe'),'{{{student.userlink}}}','{{{assignment.titlelink}}}')
        ),
        'student_assignment_evaluation'=>array(
            'description'=> __('Student : Results available for assignment','vibe'),
            'subject' =>  sprintf(__('Results available for assignment %s','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('You obtained %s out of %s in assignment %s ','vibe'),'{{assignment.marks}}','{{assignment.max}}','{{{assignment.titlelink}}}')
        ),
        'instructor_assignment_evaluation'=>array(
            'description'=> __('Instructor : Student results available for assignment','vibe'),
            'subject' =>  sprintf(__('Assignment %s evaluated for Student','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('Student %s got %s from %s in assignment %s','vibe'),'{{{student.userlink}}}','{{assignment.marks}}','{{assignment.max}}','{{{assignment.titlelink}}}')
        ),
        'student_assignment_reset'=>array(
            'description'=> __('Student : assignment reset','vibe'),
            'subject' =>  sprintf(__('Assignment %s was reset','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('Assignment %s was reset by Instructor','vibe'),'{{{assignment.titlelink}}}')
        ),
        'instructor_assignment_reset'=>array(
            'description'=> __('Instructor : assignment reset for Student','vibe'),
            'subject' =>  sprintf(__('assignment %s reset for Student','vibe'),'{{assignment.name}}'),
            'message' =>  sprintf(__('assignment %s was reset for Student %s','vibe'),'{{{assignment.titlelink}}}','{{{student.userlink}}}')
        ),
        'student_user_course_application'=>array(
            'description'=> __('Student : Applied for Course','vibe'),
            'subject' =>  sprintf(__('You applied for course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You applied for course %s ','vibe'),'{{{course.titlelink}}}')
        ),
        'instructor_user_course_application'=>array(
            'description'=> __('Instructor : Student applied for course','vibe'),
            'subject' =>  sprintf(__('Student applied for course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s applied for course %s','vibe'),'{{{student.userlink}}}','{{{course.titlelink}}}')
        ),
        'student_manage_user_application'=>array(
            'description'=> __('Student : Manage application Course','vibe'),
            'subject' =>  sprintf(__('Your application was %s for course %s','vibe'),'{{course.application_status}}','{{course.name}}'),
            'message' =>  sprintf(__('Your application was %s for course %s','vibe'),'{{course.application_status}}','{{course.name}}'),
        ),
        'instructor_manage_user_application'=>array(
            'description'=> __('Instructor : Student applied for course','vibe'),
            'subject' =>  sprintf(__('Student applied %s for course %s','vibe'),'{{course.application_status}}','{{course.name}}'),
            'message' =>  sprintf(__('Student %s application was %s for course %s','vibe'),'{{{student.userlink}}}','{{course.application_status}}','{{{course.titlelink}}}')
        ),
        'instructor_course_go_live'=>array(
            'description'=> __('Instructor : Go Live/Publish a Course','vibe'),
            'subject' =>  sprintf(__('You %s the course %s','vibe'),'{{course.publish_status}}','{{course.name}}'),
            'message' =>  sprintf(__('You  %s the course %s','vibe'),'{{course.publish_status}}','{{{course.titlelink}}}')
        ),
        'admin_course_go_live'=>array(
            'description'=> __('Administrator : Go Live/Publish a Course','vibe'),
            'subject' =>  sprintf(__('Instructor set the course %s to %s','vibe'),'{{course.publish_status}}','{{course.name}}'),
            'message' =>  sprintf(__('Instructor %s set the course  %s to status %s','vibe'),'{{{course.instructorlink}}}','{{{course.titlelink}}}','{{course.publish_status}}')
        ),
        'wplms_drip_mail'=>array(
            'description'=> __('Student : Drip Course Unit available ','vibe'),
            'subject' =>  sprintf(__('Unit %s now available in course %s','vibe'),'{{unit.name}}','{{course.name}}'),
            'message' =>  sprintf(__('Unit %s is now available in course %s','vibe'),'{{unit.name}}','{{{course.titlelink}}}')
        ),
        'wplms_expire_mail'=>array(
            'description'=> __('Student : Course about to expire ','vibe'),
            'subject' =>  sprintf(__('Subscription for course %s will expire soon','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Your subscription to course %s will expire soon','vibe'),'{{{course.titlelink}}}')
        ),
    );
    return apply_filters('bp_course_all_mails',$bp_course_mails);
}


/*===== END INTEGRATION === */
