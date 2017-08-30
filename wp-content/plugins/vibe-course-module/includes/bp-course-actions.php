<?php
/**
 * Action functions for Course Module
 *
 * @author      VibeThemes
 * @category    Admin
 * @package     Vibe Course Module
 * @version     2.0
 */

 if ( ! defined( 'ABSPATH' ) ) exit;

class BP_Course_Action{

    public static $instance;

    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new BP_Course_Action();

        return self::$instance;
    }

    private function __construct(){
    	
    	$this->nav = get_option('vibe_course_permalinks');

		add_action('bp_activity_register_activity_actions',array($this,'bp_course_register_actions'));
		add_filter( 'woocommerce_get_price_html', array($this,'course_subscription_filter'),100,2 );
		add_action('woocommerce_after_add_to_cart_button',array($this,'bp_course_subscription_product'));
		add_action( 'woocommerce_order_status_completed',array($this, 'bp_course_convert_customer_to_student' ));
		add_action('woocommerce_order_status_completed',array($this,'bp_course_enable_access'));
		add_action('woocommerce_order_status_cancelled',array($this,'bp_course_disable_access'),10,1);
		add_action('woocommerce_order_status_refunded',array($this,'bp_course_disable_access'),10,1);
		add_action('woocommerce_restock_refunded_item',array($this,'bp_course_check_partial_disable_access'),10,4);

		add_action('bp_members_directory_member_types',array($this,'bp_course_instructor_member_types'));
		add_filter('bp_course_admin_before_course_students_list',array($this,'bp_course_admin_search_course_students'),10,2);
		add_filter('wplms_course_credits',array($this,'wplms_show_new_course_student_status'),20,2);	
		add_action('wplms_before_start_course',array($this,'wplms_before_start_course_status'));
		add_action('wplms_user_course_stats',array($this,'add_course_review_button'),10,2);
		add_action('bp_course_header_actions',array($this,'bp_course_schema'));
		add_action('wplms_unit_header',array($this,'wplms_custom_unit_header'),10,2);

		add_action('wplms_course_submission_quiz_tab_content',array($this,'bp_course_get_course_quiz_submissions'),10,1);
		add_action('wplms_course_submission_course_tab_content',array($this,'bp_course_get_course_submissions'),10,1);

		//apply for course
		add_action('wplms_course_submission_applications_tab_content',array($this,'get_course_applications'),10,1);

		add_action('wp_head',array($this,'remove_woocommerce_endactions'));
		add_action('wp_footer',array($this,'offline_enqueue_footer'));

		add_action('wplms_get_quiz_result',array($this,'display_quiz_result'),10,2);
		add_action('wplms_get_user_results',array($this,'get_quiz_results'),10,1);
		add_action('wplms_quiz_results_extras',array($this,'back_to_course_button'),10,3);

		// Dynamic Quiz v2
		remove_action('wplms_before_quiz_begining','wplms_dynamic_quiz_select_questions',10,1);
		add_action('wplms_before_quiz_begining',array($this,'set_dynamic_question_set'));

		/*
		Share ACtivity
		*/
		add_action('wp_head',array($this,'add_social_ogg'));

		// Remove Course Cookies on login
		add_action('wp_login',array($this,'clear_course_history'));

		//Check answer
		add_action('wplms_quiz_question',array($this,'check_answer'),10,2);

		//
		add_action('wp_footer',array($this,'prevent_enter_on_directory'));
	}


	function prevent_enter_on_directory(){
		global $bp;
		if(bp_is_directory() || ($bp->current_action == 'admin' && $bp->current_component == 'course'))	{
		?>
		<script>
		jQuery(document).on('keyup keypress', '#course_user_ajax_search_results input[type="text"],#member-dir-search input[type="text"],#group-dir-search input[type="text"],#course-directory-form input[type="text"]', function(e) {
	      	if(e.which == 13) {
	        	e.preventDefault();
	        	return false;
	      	}
	    });
		</script>
		<?php
		}
	}
	function clear_course_history(){
		setcookie('course',null,0,'/');
	}

	function add_social_ogg(){
		global $bp;

		if(!empty($bp->current_action ) && $bp->current_component=='activity'){
			global $wpdb;

			$activity = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bp->activity->table_name} WHERE id=%d",$bp->current_action));
			if($activity->type=='quiz_evaluated'){
				if(!empty($activity->item_id) && bp_course_get_post_type($activity->item_id)=='course'){
	    			$thumbnail = wp_get_attachment_url( get_post_thumbnail_id($activity->item_id) );
				}elseif(!empty($activity->secondary_item_id) && bp_course_get_post_type($activity->secondary_item_id)=='course'){
	   				$thumbnail = wp_get_attachment_url( get_post_thumbnail_id($activity->secondary_item_id) );
	  			}else{
	   				$thumbnail = apply_filters('meta_og_post_thumbnail','',$activity);
				}
				$content=strip_tags ($activity->content);
				echo '<meta property="og:url" content="'.$activity->primary_link.'" />
					<meta property="og:type" content="'.$activity->type.'" />
					<meta property="og:title" content="'.$activity->action.'" />
					<meta property="og:description" content="'.$content.'" />
					'.(empty($thumbnail)?'':'<meta property="og:image" content="'.$thumbnail.'" />');
			}
		}
	}

	function offline_enqueue_footer(){
		global $post;
		if(!empty($post) && $post->post_type == 'course'){
			$vibe_course_unit_content = get_post_meta($post->ID,'vibe_course_unit_content',true);
			if(!empty($vibe_course_unit_content) && $vibe_course_unit_content == 'S'){
				wp_enqueue_style('wp-mediaelement');
				wp_enqueue_script('wp-mediaelement');
			}
		}
	}
	function remove_woocommerce_endactions(){
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
	}

	function bp_course_register_actions(){
		global $bp; 
		$bp_course_action_desc=array(
			'remove_from_course' => __( 'Removed a student from Course', 'vibe' ),
			'submit_course' => __( 'Student submitted a Course', 'vibe' ),
			'start_course' => __( 'Student started a Course', 'vibe' ),
			'submit_quiz' => __( 'Student submitted a Quiz', 'vibe' ),
			'start_quiz' => __( 'Student started a Course', 'vibe' ),
			'unit_complete' => __( 'Student submitted a Course', 'vibe' ),
			'reset_course' => __( 'Course reset for Student', 'vibe' ),
			'bulk_action' => __( 'Bulk action by instructor', 'vibe' ),
			'course_evaluated' => __( 'Course Evaluated for student', 'vibe' ),
			'student_badge'=> __( 'Student got a Badge', 'vibe' ),
			'student_certificate' => __( 'Student got a certificate', 'vibe' ),
			'quiz_evaluated' => __( 'Quiz Evaluated for student', 'vibe' ),
			'subscribe_course' => __( 'Student subscribed for course', 'vibe' ),
			);
		foreach($bp_course_action_desc as $key => $value){
			bp_activity_set_action($bp->activity->id,$key,$value);	
		}
	}


	function course_subscription_filter($price,$product){

		$subscription=get_post_meta($product->id,'vibe_subscription',true);

			if(vibe_validate($subscription)){
				$duration = intval(get_post_meta($product->id,'vibe_duration',true));

				$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400,$product->id);
				$t=$duration * $product_duration_parameter;
				if($duration == 1){
					$price = $price .'<span class="subs"> '.__('per','vibe').' '.tofriendlytime($t,$product_duration_parameter).'</span>';
				}else{
					$price = $price .'<span class="subs"> '.__('per','vibe').' '.tofriendlytime($t,$product_duration_parameter).'</span>';
				}
			}
			return $price;
	}





	function bp_course_subscription_product(){
		global $product;
		$check_susbscription=get_post_meta($product->id,'vibe_subscription',true);
		if(vibe_validate($check_susbscription)){
			$duration=intval(get_post_meta($product->id,'vibe_duration',true));	
			
			$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400,$product->id);
			$t=tofriendlytime($duration*$product_duration_parameter);
			echo '<div id="duration"><strong>'.__('SUBSCRIPTION FOR','vibe').' '.$t.'</strong></div>';
		}
	}

	function bp_course_convert_customer_to_student( $order_id ) {
	    $order = new WC_Order( $order_id );
	    if ( $order->user_id > 0 ) {
	        $user = new WP_User( $order->user_id );
	        $user->remove_role( 'customer' ); 
	        $user->add_role( 'student' );
	    }
	}



	function bp_course_enable_access($order_id){

		$order = new WC_Order( $order_id );

		$items = $order->get_items();
		$user_id=$order->user_id;
		$order_total = $order->get_total();
		$commission_array=array();

		foreach($items as $item_id=>$item){

		$instructors=array();
		
		$courses=get_post_meta($item['product_id'],'vibe_courses',true);

		$product_id = apply_filters('bp_course_product_id',$item['product_id'],$item);
		$subscribed=get_post_meta($product_id,'vibe_subscription',true);

		if(isset($courses) && is_array($courses)){

			if(vibe_validate($subscribed) ){

				$duration = get_post_meta($product_id,'vibe_duration',true);
				$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400,$product_id);
				$total_duration = $duration*$product_duration_parameter;
				foreach($courses as $course){
					bp_course_add_user_to_course($user_id,$course,$total_duration,1);
			        $instructors[$course]=apply_filters('wplms_course_instructors',get_post_field('post_author',$course),$course);
			        do_action('wplms_course_product_puchased',$course,$user_id,$total_duration,1,$product_id);
				}
			}else{	
				if(isset($courses) && is_array($courses)){
				foreach($courses as $course){
						bp_course_add_user_to_course($user_id,$course,'',1);
		        		$instructors[$course]=apply_filters('wplms_course_instructors',get_post_field('post_author',$course,'raw'),$course);
			        	do_action('wplms_course_product_puchased',$course,$user_id,0,0,$product_id);
					}
				}
			}//End Else

				$line_total=$item['line_total'];

			//Commission Calculation
			$commission_array[$item_id]=array(
				'instructor'=>$instructors,
				'course'=>$courses,
				'total'=>$line_total,
			);

		  }//End If courses
		}// End Item for loop
		
		if(function_exists('vibe_get_option'))
	      $instructor_commission = vibe_get_option('instructor_commission');
	    
	    if($instructor_commission == 0)
	      		return;
	      	
	    if(!isset($instructor_commission) || !$instructor_commission)
	      $instructor_commission = 70;

	    $commissions = get_option('instructor_commissions');

		foreach($commission_array as $item_id=>$commission_item){

				foreach($commission_item['course'] as $course_id){ 
				
				if(count($commission_item['instructor'][$course_id]) > 1){     // Multiple instructors
					
					$calculated_commission_base=round(($commission_item['total']*($instructor_commission/100)/count($commission_item['instructor'][$course_id])),0); // Default Slit equal propertion

					foreach($commission_item['instructor'][$course_id] as $instructor){
						if(empty($commissions[$course_id][$instructor])){
							$calculated_commission_base = round(($commission_item['total']*$instructor_commission/100),2);
						}else{
							$calculated_commission_base = round(($commission_item['total']*$commissions[$course_id][$instructor]/100),2);
						}
						$calculated_commission_base = apply_filters('wplms_calculated_commission_base',$calculated_commission_base,$instructor);
						woocommerce_update_order_item_meta( $item_id, '_commission'.$instructor,$calculated_commission_base);
					}
				}else{
					if(is_array($commission_item['instructor'][$course_id]))                                    // Single Instructor
						$instructor=$commission_item['instructor'][$course_id][0];
					else
						$instructor=$commission_item['instructor'][$course_id]; 
					
					if(isset($commissions[$course_id][$instructor]) && is_numeric($commissions[$course_id][$instructor]))
						$calculated_commission_base = round(($commission_item['total']*$commissions[$course_id][$instructor]/100),2);
					else
						$calculated_commission_base = round(($commission_item['total']*$instructor_commission/100),2);

					$calculated_commission_base = apply_filters('wplms_calculated_commission_base',$calculated_commission_base,$instructor);
					woocommerce_update_order_item_meta( $item_id, '_commission'.$instructor,$calculated_commission_base);
				}   
			}

		} // End Commissions_array  
	}

	function bp_course_disable_access($order_id){
		$order = new WC_Order( $order_id );

		$items = $order->get_items();
		$user_id=$order->user_id;
		foreach($items as $item){
			$product_id = $item['product_id'];
			$subscribed=get_post_meta($product_id,'vibe_subscription',true);
			$courses=vibe_sanitize(get_post_meta($product_id,'vibe_courses',false));
			if(isset($courses) && is_array($courses)){

				if($user_id == get_current_user_id()){
					return; // Do not run when user herself cancels an order because we are not sure if it is the same order.
				}
				foreach($courses as $course){


					bp_course_remove_user_from_course($user_id,$course);
					if(function_exists('vibe_get_option'))
				      $instructor_commission = vibe_get_option('instructor_commission');
				    
				    if(empty($instructor_commission))
				      		return;
					$instructors = apply_filters('wplms_course_instructors',get_post_field('post_author',$course,'raw'),$course);
					if(is_array($instructors)){
						foreach($instructors as $instructor){
							woocommerce_update_order_item_meta( $item_id, '_commission'.$instructor,0);//Nulls the commission
						}
					}
				}
			}
		} 
	}
	//Partial refund use case
	function bp_course_check_partial_disable_access($product_id, $old_stock, $new_quantity, $order ){
		$user_id=$order->user_id;
		$courses=vibe_sanitize(get_post_meta($product_id,'vibe_courses',false));
		if(isset($courses) && is_array($courses)){
			foreach($courses as $course){
				bp_course_remove_user_from_course($user_id,$course);
				if(function_exists('vibe_get_option'))
			      	$instructor_commission = vibe_get_option('instructor_commission');
			    
			    if(empty($instructor_commission))
		      		return;

		      	$order_items = $order->get_items();
		      	foreach ($order_items as $order_item_id => $order_item) { 
		      		if($order_item['product_id'] == $product_id){
		      			$item_id = $order_item_id;
		      			break;
		      		}
				}
				
				$instructors = apply_filters('wplms_course_instructors',get_post_field('post_author',$course),$course);
				if(!is_array($instructors)){
					$instructors = array($instructors);
				}
				if(is_array($instructors)){
					foreach($instructors as $instructor){
						woocommerce_update_order_item_meta( $item_id, '_commission'.$instructor,0);//Nulls the commission
					}
				}
			}
		}
	}

	function bp_course_instructor_member_types(){
		?>
			<li id="members-instructors"><a href="#"><?php printf( __( 'All Instructors <span>%s</span>', 'vibe' ), bp_get_total_instructor_count() ); ?></a></li>
		<?php
	}


	function wplms_custom_unit_header($unit_id,$course_id){
		if(bp_course_get_post_type($unit_id) == 'quiz'){
			in_quiz_timer(array('quiz_id'=>$unit_id));
		}else{
			if(bp_course_get_post_type($unit_id) == 'unit'){
				the_unit_tags($unit_id);
				$id = $unit_id;
			}else{
				$id = $course_id;
			}
	        the_unit_instructor($id);  
		}
	}
	function bp_course_admin_search_course_students($students,$course_id){

		$course_statuses = apply_filters('wplms_course_status_filters',array(
			1 => __('Start Course','vibe'),
			2 => __('Continue Course','vibe'),
			3 => __('Under Evaluation','vibe'),
			4 => __('Course Finished','vibe')
			));
		$active_statuses = apply_filters('wplms_course_active_status_filters',array(
			1 => __('Active','vibe'),
			2 => __('Expired','vibe'),
			));
		echo '<form id="course_user_ajax_search_results" data-id="'.$course_id.'">
				<select id="active_status"><option value="">'.__('Filter by Status','vibe').'</option>';
				foreach($active_statuses as $key =>$value){
					echo '<option value="'.$key.'">'.$value.'</option>';
				}
		echo  '</select>
				<select id="course_status"><option value="">'.__('Filter by Status','vibe').'</option>';
				foreach($course_statuses as $key =>$value){
					echo '<option value="'.$key.'">'.$value.'</option>';
				}
		echo  '</select>';
		do_action('wplms_course_admin_form',$students,$course_id);
		echo '<span id="search_course_member"><input type="text" name="search" placeholder="'.__('Enter student name/email','vibe').'" /></span>
			 </form>';

		return $students;
	}

	
	function wplms_show_new_course_student_status($credits,$course_id){

	  if(is_user_logged_in() && !is_singular('course')){
	    $user_id=get_current_user_id();
	    $check=get_user_meta($user_id,$course_id,true);
	    if(isset($check) && $check){
	      if($check < time()){
	        return '<a href="'.get_permalink($course_id).'"><strong>'.sprintf(__('EXPIRED %s COURSE','vibe'),'<span class="subs">').'</span></strong></a>';
	      }

	      $check_course= bp_course_get_user_course_status($user_id,$course_id);
	      $new_check_course = get_user_meta($user_id,'course_status'.$course_id,true);
	      if(isset($new_check_course) && is_numeric($new_check_course) && $new_check_course){
	  	      switch($check_course){
		        case 1:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.sprintf(__('START %s COURSE','vibe'),'<span class="subs">').'</span></strong></a>';
		        break;
		        case 2:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.sprintf(__('CONTINUE %s COURSE','vibe'),'<span class="subs">').'</span></strong></a>';
		        break;
		        case 3:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.sprintf(__('UNDER %s EVALUATION','vibe'),'<span class="subs">').'</span></strong></a>';
		        break;
		        case 4:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.sprintf(__('FINISHED %s COURSE','vibe'),'<span class="subs">').'</span></strong></a>';
		        break;
		        default:
		        $credits =apply_filters('wplms_course_status_display','<a href="'.get_permalink($course_id).'"><strong>'.sprintf(__('COURSE %s ENABLED','vibe'),'<span class="subs">').'</span></strong></a>',$course_id);
		        break;
		      }
	      }else{
	      		switch($check_course){
		        case 0:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('START','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        case 1:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('CONTINUE','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        case 2:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('UNDER','vibe').'<span class="subs">'.__('EVALUATION','vibe').'</span></strong></a>';
		        break;
		        default:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('FINISHED','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		      }	
	      }
	    }
	  }

	  return $credits;
	}


	function wplms_before_start_course_status(){
	  $user_id = get_current_user_id();  
	  
	  if ( isset($_POST['start_course']) && wp_verify_nonce($_POST['start_course'],'start_course'.$user_id) ){
	      $course_id=$_POST['course_id'];
	      $coursetaken=1;
	      $cflag=0;
	      $precourse=get_post_meta($course_id,'vibe_pre_course',true);

	      if(!empty($precourse)){
	      		$pre_course_check_status = apply_filters('wplms_pre_course_check_status_filter',2);
	          	if(is_numeric($precourse)){
	          		$preid=bp_course_get_user_course_status($user_id,$precourse);
		          	if(!empty($preid) && $preid >  $pre_course_check_status){ 
			            // COURSE STATUSES : Since version 1.8.4
			            // 1 : START COURSE
			            // 2 : CONTINUE COURSE
			            // 3 : FINISH COURSE : COURSE UNDER EVALUATION
			            // 4 : COURSE EVALUATED
			              $cflag=1;
			          }
	          	}else if(is_array($precourse)){
		          	foreach($precourse as $pc){
		          		$preid=bp_course_get_user_course_status($user_id,$pc);
			          	if(!empty($preid) && $preid > $pre_course_check_status){ 
				              $cflag=1;
				        }else{
				        	//Break from loop
				        	break;
				        }
		          	}
	          	}
	      }else{
	          $cflag=1;
	      }

	      if($cflag){
	          
	          $course_duration_parameter = apply_filters('vibe_course_duration_parameter',86400,$course_id);
	          $expire=time()+$course_duration_parameter; // One Unit logged in Limit for the course
	          setcookie('course',$course_id,$expire,'/');
	          bp_course_update_user_course_status($user_id,$course_id,1);//Since version 1.8.4
	          do_action('wplms_start_course',$course_id,$user_id);
	      }else{
	          
	          header('Location: ' . get_permalink($course_id) . '?error=precourse');
	          
	      }

	    

	  }else if ( isset($_POST['continue_course']) && wp_verify_nonce($_POST['continue_course'],'continue_course'.$user_id) ){
	    $course_id=$_POST['course_id'];
	    $coursetaken=get_user_meta($user_id,$course_id,true);
	      setcookie('course',$course_id,$expire,'/');
	  }else{
	    if(isset($_COOKIE['course'])){
	      $course_id=$_COOKIE['course'];
	      $coursetaken=1;
	    }else
	      wp_die( __('This Course can not be taken. Contact Administrator.','vibe'), 'Contact Admin', array(500,true) );
	  }

	}



	function add_course_review_button($user_id,$course_id){
		global $wpdb;
		$check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND user_id=%d",$course_id,$user_id));
		if(empty($check))
		echo '<form action="'.get_permalink($course_id).'" method="post">
	 	<button type="submit" style="float: right;padding: 5px 7px 0 7px;border:none;background:none;" name="review_course" class="review_course tip" title="'.__('REVIEW COURSE ','vibe').'"/><i class="icon-comment tip" style="color:#666;font-size: 24px;"></i></button>'.wp_nonce_field($course_id,'review').'</form>';
	}


	function bp_course_schema(){
		global $post;
		$key = 'course_microdata'.$post->ID;

		if(!isset($_GET['clear'])){
			$meta = get_transient( $key );
		}

		if(empty($meta) || false === $meta){
			ob_start();
			?> 
			<div itemscope itemtype="http://schema.org/Product">
		    	<meta itemprop="brand" content="<?php echo get_bloginfo( 'name', 'display' ); ?>" /> 
				<meta itemprop="name" content="<?php echo $post->post_title; ?>"/> 
				<meta itemprop="description" content="<?php echo strip_tags(get_the_excerpt()); ?>"/> 
				<?php $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID,'full') );?>
				<meta itemprop="image" content="<?php echo $url; ?>"/> 
				<?php
					$rating = get_post_meta(get_the_ID(),'average_rating',true);
					$count = get_post_meta(get_the_ID(),'rating_count',true);
					if(!empty($rating)){
				?>
				<span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"> 
					<meta itemprop="ratingValue" content="<?php echo $rating; ?>"/> <meta itemprop="reviewCount" content="<?php echo $count; ?>"/> 
				</span>
				<?php
					}
				if(function_exists('get_woocommerce_currency')){
					$product_id = get_post_meta(get_the_ID(),'vibe_product',true);
					if(!empty($product_id)){
						$sale = get_post_meta( $product_id, '_sale_price', true);
						$price = get_post_meta( $product_id, '_regular_price', true);
						$currency = get_woocommerce_currency();
					?>
					<span itemprop="offers" itemscope itemtype="http://schema.org/Offer">
						<meta itemprop="price" content="<?php echo (empty($sale)?$price:$sale); ?>" />
						<meta itemprop="priceCurrency" content="<?php echo (empty($currency)?'USD':$currency); ?>" />
					</span>
					<?php
					}
				}
				?>
			</div>
			<?php
			$meta = ob_get_clean();
			set_transient( $key , $meta, 24 * HOUR_IN_SECONDS );
		}
		echo $meta;
	}



	function bp_course_get_course_submissions($course_id){
		?>
			<div class="submissions_form">
				<select id="fetch_course_status">
					<option value="3"><?php echo _x('Pending evaluation','Course status','vibe') ?></option>
					<option value="4"><?php echo _x('Evaluation complete','Course status','vibe') ?></option>
				</select>
				<?php wp_nonce_field('pending_course_submissions','pending_course_submissions'); ?>
				<a id="fetch_course_submissions" class="button"><?php echo _x('Get','get quiz submissions button','vibe'); ?></a>
			</div>
			<script>
				jQuery(document).ready(function($){
					$('#fetch_course_submissions').on('click',function(){
						var $this = $(this);
						var parent = $(this).parent();
						$('.course_students').remove();
						$this.append('<i class="fa fa-spinner"></i>');
						$('.message').remove();
						$.ajax({
	                      	type: "POST",
	                      	url: ajaxurl,
	                      	data: { action: 'fetch_course_submissions', 
	                              	security: $('#pending_course_submissions').val(),
	                              	course_id:<?php echo $course_id; ?>,
	                              	status:$('#fetch_course_status').val(),
	                            	},
	                      	cache: false,
	                      	success: function (html) {
	                      		parent.after(html);
	                      		$this.find('.fa').remove();
	                      		$('#course').trigger('loaded');
	                      	}
	                    });
					});
				});
			</script>
		<?php		
	}

	function bp_course_get_course_quiz_submissions($course_id){

		$quizes = bp_course_get_curriculum_quizes($course_id);

		if(!empty($quizes)){
			$quiz_ids = implode(',',$quizes);
			global $wpdb;
			$count_array = array();
	  		$submissions = $wpdb->get_results($wpdb->prepare("SELECT count(*) as count,u.meta_key as quiz_id FROM {$wpdb->postmeta} as p LEFT JOIN {$wpdb->usermeta} as u ON p.meta_key = u.user_id WHERE p.meta_value LIKE '0' AND u.meta_key = p.post_id AND u.meta_value < %d AND p.post_id IN ($quiz_ids) GROUP BY u.meta_key",time()));
	  		if(!empty($submissions)){
	  			foreach($submissions as $submission){
		  			$count_array[$submission->quiz_id]=$submission->count;
		  		}	
	  		}
			?>
			<div class="submissions_form">
				<select id="fetch_quiz">
				<?php
				foreach($quizes as $quiz_id){
					?>
					<option value="<?php echo $quiz_id; ?>"><?php echo get_the_title($quiz_id); ?> (<?php echo (empty($count_array[$quiz_id])?0:$count_array[$quiz_id]);?>)</option>
					<?php	
				}
				?>
				</select>
				<select id="fetch_status">
					<option value="0"><?php echo _x('Pending evaluation','Quiz status','vibe') ?></option>
					<option value="1"><?php echo _x('Evaluation complete','Quiz status','vibe') ?></option>
				</select>
				<?php wp_nonce_field('quiz_submissions','quiz_submissions'); ?>
				<a id="fetch_quiz_submissions" class="button"><?php echo _x('Get','get quiz submissions button','vibe'); ?></a>
			</div>
			<script>
				jQuery(document).ready(function($){
					$('#fetch_quiz_submissions').on('click',function(){
						var parent = $(this).parent();
						$('.quiz_students').remove();
						$('.message').remove();
						var $this = $(this);
						$this.append('<i class="fa fa-spinner"></i>');
						$.ajax({
	                      	type: "POST",
	                      	url: ajaxurl,
	                      	data: { action: 'fetch_quiz_submissions', 
	                              	security: $('#quiz_submissions').val(),
	                              	quiz_id:$('#fetch_quiz').val(),
	                              	status:$('#fetch_status').val(),
	                            	},
	                      	cache: false,
	                      	success: function (html) {
	                      		parent.after(html);
	                      		$this.find('.fa').remove();
	                      		$('#quiz').trigger('loaded');
	                      	}
	                    });
					});
				});
			</script>
			<?php
		}else{
			?>
			<div class="message">
				<p><?php echo _x('No Quiz found !','No quizzes in course, error on course submissions','vibe'); ?></p>
			</div>
			<?php
		}
	}

	function get_course_applications($course_id){
		global $wpdb;

		$users = $wpdb->get_results("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'apply_course' AND meta_value = $course_id");

		if(count($users)){
			echo '<ul>';
			foreach($users as $user){
            ?>
				<li class="user clear" data-id="<?php echo $user->user_id; ?>" data-course="<?php echo $course_id; ?>" data-security="<?php echo wp_create_nonce('security'.$course_id.$user->user_id); ?>">
					<?php echo get_avatar($user->user_id).bp_core_get_userlink( $user->user_id );?>
					<span class="reject"><?php echo _x('Reject','reject user application for course','vibe'); ?></span>
					<span class="approve"><?php echo _x('Approve','approve user application for course','vibe'); ?></span>
				</li>
            <?php
        	}
        	echo '</ul>';
        }else{
            ?>
            <div class="message">
                <p><?php echo _x('No applications found !','No applications found in course, error on course submissions','vibe'); ?></p>
            </div>
            <?php
        }
	}

	//Get Quiz results list
	function get_quiz_results($user_id){

		$paged = 1;
		$per_page = 5;
	    if(function_exists('vibe_get_option')){
        	$per_page = vibe_get_option('loop_number');  
        }
		$the_quiz=new WP_QUERY(array(
			'post_type'=>'quiz',
			'paged'=>$paged,
			'posts_per_page'=>$per_page,
			'meta_query'=>array(
				array(
					'key' => $user_id,
					'compare' => 'EXISTS'
					),
				),
			));

			if($the_quiz->have_posts()){

		?>
		<h3 class="heading"><?php _e('Quiz Results','vibe'); ?></h3>
		<div class="user_results">
			<ul class="quiz_results">
			<?php
				while($the_quiz->have_posts()) : $the_quiz->the_post();
				global $post;
					$this->get_quiz_item($post,$user_id);
				endwhile;
				?>
			</ul>
			<?php
				if($the_quiz->max_num_pages>1){?>
						<div class="pagination no-ajax">
						<div class="pag-count">
							<?php echo sprintf(__('Viewing %d out of %d','vibe'),$paged,$the_quiz->max_num_pages) ?>
						</div>
						<div class="pagination-links">
							<?php
						    for($i=1;$i<=$the_quiz->max_num_pages;$i++){
						    	if(($paged==$i)){
						    		?>
						    		<span class="page-numbers current"><?php echo $i;?></span>
						    		<?php
						    	}else{
						    		?>
						    		<a class="page-numbers get_results_pagination" data-type="quiz"><?php echo $i;?></a>
						    		<?php
						    	}
						    }
						    ?>
						</div>
					</div>
				<?php }
				wp_reset_query();
			?>
			</div>
			<?php
			wp_nonce_field('security','security');
  		}
	}

	function get_quiz_item($post,$user_id){
		$value = get_post_meta($post->ID,$user_id,true);
		$questions = bp_course_get_quiz_questions($post->ID,$user_id);
		if(is_Array($questions['marks']) && isset($questions['marks']))
			$max = array_sum($questions['marks']);
		else
			$max = 0; 
		?>
		<li><i class="icon-task"></i>
			<a href="?action=<?php echo $post->ID; ?>"><?php echo $post->post_title; ?></a>
			<span><?php	
			$status = bp_course_get_user_quiz_status($user_id,$post->ID);
			if($status > 0){
				echo '<i class="icon-check"></i> '.__('Results Available','vibe');
			}else{
				echo '<i class="icon-alarm"></i> '.__('Results Awaited','vibe');
			}
			?></span>
			<span><?php
			$newtime=get_user_meta($user_id,$post->ID,true);
			if(!empty($newtime) && is_numeric($newtime)){
				$diff = time() - $newtime;
				if($diff > 0){
					echo '<i class="icon-clock"></i> '.__('Submitted ','vibe').tofriendlytime($diff) .__(' ago','vibe');
				}else{
					echo '<i class="icon-clock"></i> '.__(' Pending Submission','vibe');
				}
			}
			?></span>
			<?php
			if($status > 0)
				echo '<span><strong>'.$value.' / '.$max.'</strong></span>';
			?>
		</li>
		<?php
	}

	function display_quiz_result($quiz_id,$user_id){
		$course=get_post_meta($quiz_id,'vibe_quiz_course',true);
		echo '<div class="user_quiz_result">';
		
		if(function_exists('bp_course_quiz_results'))
			bp_course_quiz_results($quiz_id,$user_id,$course);

			do_action('wplms_quiz_results_extras',$quiz_id,$user_id,$course);
		
		if(function_exists('bp_course_quiz_retake_form'))
			bp_course_quiz_retake_form($quiz_id,$user_id,$course);	

		echo '</div>';
	}

	function back_to_course_button($quiz_id,$user_id,$course_id = null){
		if(!empty($course_id) && !defined('DOING_AJAX')){
			echo '<a href="'.get_permalink($course_id).'" class="button back_to_course">&larr;&nbsp; '.sprintf(_x('Back to course %s','Back to course button in quiz results area in profile - course - results section','vibe'),get_the_title($course_id)).'</a>';
		}
	}

	function set_dynamic_question_set($quiz_id=NULL){
	  	
	  	
	  	if(!isset($quiz_id)){
	    	global $post;  
	    	$quiz_id = $post->ID;
	  	}

	  	
	  	if(empty($quiz_id) || !is_user_logged_in())
	  		return;

	  	$user_id = get_current_user_id();

	  	if(!is_object($post) && $post->post_type != 'quiz'){
	  		$quiz_author = get_post_field('post_author',$quiz_id);
	  	}else{
	  		$quiz_author = $post->post_author;
	  	}

	  	
	    if($user_id != $quiz_author && !current_user_can('manage_options')){
	    	
	        $quiz_questions = bp_course_get_quiz_questions($quiz_id,$user_id);  
	        if(isset($quiz_questions) && $quiz_questions && is_array($quiz_questions) && count($quiz_questions)){
	            return;
	        }
	    }else{
	    	$quiztaken=get_user_meta($user_id,$quiz_id,true);
	        if(isset($quiztaken) && $quiztaken){
	            return;
	        }
	    }
	  

	    $quiz_dynamic = get_post_meta($quiz_id,'vibe_quiz_dynamic',true);
	    $quiz_questions = array('ques'=>array(),'marks'=>array()); 
	    if(vibe_validate($quiz_dynamic)){ 

	    	// DYNAMIC QUIZ QUIZ
	    	$marks = get_post_meta($quiz_id,'vibe_quiz_marks_per_question',true);
	        $alltags = get_post_meta($quiz_id,'vibe_quiz_tags',true);
	        if(is_array($alltags) && !empty($alltags) && isset($alltags['tags']) && isset($alltags['numbers'])){
	        	
	        	//DYNAMIC QUIZ V 2
	        	foreach($alltags['tags'] as $key=>$tags){
	        		
	        		if(!is_array($tags)){
	        			$tags = unserialize($tags);
	        		}
	        		$number = $alltags['numbers'][$key];
	        		if(empty($number)){
	        			$number = get_post_meta($quiz_id,'vibe_quiz_number_questions',true);
	        			if(empty($number)){
	        				$number = 0;
	        			}
	        		}

	        		
	        		$args = apply_filters('bp_course_dynamic_quiz_tag_questions',array(
		                'post_type' => 'question',
		                'orderby' => 'rand', 
		                'posts_per_page' => $number,
		                'tax_query' => array(
		                  	array(
		                    	'taxonomy' => 'question-tag',
		                    	'field' => 'id',
		                    	'terms' => $tags,
		                    	'operator' => 'IN'
		                  	),
		                )
			        ),$alltags);

	        		if(!empty($quiz_questions['ques'])){
	        			$args['post__not_in'] = $quiz_questions['ques'];
	        		}

			        if($number){
			        	$the_query = new WP_Query( $args );
			        	if($the_query->have_posts()){
			        		while ( $the_query->have_posts() ) {
					            $the_query->the_post();
					            $quiz_questions['ques'][]=get_the_ID();
					            $quiz_questions['marks'][]=$marks;
					        }
			        	}
				        wp_reset_postdata();
			        }
	        	}

	        }else{

	        	//DYNAMIC QUIZ V 1
	        	
	        	$tags = $alltags;
	        	$number = get_post_meta($quiz_id,'vibe_quiz_number_questions',true);	
	        	if(!isset($number) || !is_numeric($number)) $number=0;
	        	$args = array(
	                'post_type' => 'question',
	                'orderby' => 'rand', 
	                'posts_per_page' => $number,
	                'tax_query' => array(
	                  	array(
	                    	'taxonomy' => 'question-tag',
	                    	'field' => 'id',
	                    	'terms' => $tags
	                  	),
	                )
		        );
		        $the_query = new WP_Query( $args );
		        while ( $the_query->have_posts() ) {
		            $the_query->the_post();
		            $quiz_questions['ques'][]=get_the_ID();
		            $quiz_questions['marks'][]=$marks;
		        }
		        wp_reset_postdata();
	        }
	        
	        
	    }else{

	    	// STATIC QUIZ
	    	if(empty($quiz_questions))
	        	$quiz_questions = vibe_sanitize(get_post_meta($quiz_id,'vibe_quiz_questions',false));

	        $randomize=get_post_meta($quiz_id,'vibe_quiz_random',true);
	        if(isset($randomize) && $randomize == 'S'){ // If Radomise is not set.
	            if(isset($quiz_questions['ques']) && is_array($quiz_questions['ques']) && count($quiz_questions['ques']) > 1){
	                $randomized_keys = array_rand($quiz_questions['ques'], count($quiz_questions['ques'])); 
	                shuffle($randomized_keys);
	                foreach($randomized_keys as $current_key) { 
	                    $rand_quiz_questions['ques'][] = $quiz_questions['ques'][$current_key];
	                    $rand_quiz_questions['marks'][] = $quiz_questions['marks'][$current_key]; 
	                }
	            }
	            $quiz_questions = $rand_quiz_questions;   
	        }
	    }
	    bp_course_update_quiz_questions($quiz_id,$user_id,$quiz_questions);
	}

	//Check quiz answer
	function check_answer($question_id,$quiz_id){
		$user_id = get_current_user_id();
		$check = get_post_meta($quiz_id,'vibe_quiz_check_answer',true);
		if(!empty($check) && $check == 'S'){
			$marks = bp_course_get_user_question_marks($quiz_id,$question_id,$user_id);
			if(!is_numeric($marks)){
				echo '<a class="small button check_question_answer" data-security="'.wp_create_nonce($user_id.$quiz_id.$question_id).'" data-user="'.$user_id.'" data-quiz="'.$quiz_id.'" data-question="'.$question_id.'">'.__('Check Answer','vibe').'</a>';
			}
		}
	}
}


BP_Course_Action::init();


function bp_course_get_nav_permalinks(){
	$action = BP_Course_Action::init();
	return $action->nav;
}