<?php

/**
 * NOTE: You should always use the wp_enqueue_script() and wp_enqueue_style() functions to include
 * javascript and css files.
 */


 if ( ! defined( 'ABSPATH' ) ) exit;
function bp_course_add_css() {
	if ( ! function_exists( 'vibe_logo_url' ) ) return; // Checks if WPLMS is active in current site in WP Multisite
	wp_enqueue_style( 'bp-course-css', plugins_url( '/vibe-course-module/includes/css/course_template.css' ) );
}
add_action( 'wp_enqueue_scripts', 'bp_course_add_css');


function bp_course_add_js() {
	global $bp;
	if ( ! function_exists( 'vibe_logo_url' ) ) return; // Checks if WPLMS is active in current site in WP Multisite

	$take_course_page_id = vibe_get_option('take_course_page');
	$create_course_page_id = vibe_get_option('create_course');
	if(function_exists('vibe_get_option') && function_exists('icl_object_id')){
		$take_course_page_id = icl_object_id($take_course_page_id,'page',true);
		$create_course_page_id = icl_object_id($create_course_page_id,'page',true);
	}
	wp_enqueue_script( 'bp-extras-js', plugins_url( '/vibe-course-module/includes/js/course-module-js.min.js' ),array('jquery'),bp_course_version(),true);
	if(function_exists('vibe_get_option')){
		if(is_singular('unit') || is_singular('question') || is_singular('quiz') || is_singular('wplms-assignment') || is_page($take_course_page_id) || is_page($create_course_page_id) || isset($_GET['edit']) ){
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script('jquery-ui-droppable');
			wp_enqueue_script('jquery-ui-datepicker');
			wp_enqueue_script('knob-js',plugins_url( '/vibe-course-module/includes/js/jquery.knob.min.js'),array('jquery'),bp_course_version(),true);

			add_action('wp_footer',function(){
				?>
				<script>
				var isDesktop = (function() { 
			      return !('ontouchstart' in window) // works on most browsers 
			      || !('onmsgesturechange' in window); // works on ie10
			     })();
			     //edit, if you want to use this variable outside of this closure, or later use this:
			     window.isDesktop = isDesktop;
			     if( isDesktop ){ /* desktop things */}else{
			        /* MOBILE THINGS */
			        document.write('<script type="text/javascript" src="<?php echo plugins_url( '/vibe-course-module/includes/js/jquery.uitouchpunch.js' ); ?>"><\/script>');
			     }
				</script>
				<?php
			});
		}
		if(is_page(vibe_get_option('take_course_page'))){
			wp_playlist_scripts('video');
		}
	}
	if(function_exists('bp_is_directory')){
		if((bp_is_directory() && bp_current_component() == 'course') || is_post_type_archive('course')){
			wp_enqueue_script('jquery-ui-datepicker');
		}
	}
	
	$action = bp_current_action();
	if(isset($_GET['action'])){
		$action = $_GET['action'];
	}
	if(in_array($action, array('admin','submissions','stats'))){
		wp_enqueue_script('knob-js',plugins_url( '/vibe-course-module/includes/js/jquery.knob.min.js'),array('jquery'),bp_course_version(),true);
	}
	
	wp_enqueue_script( 'bp-course-js', plugins_url( '/vibe-course-module/includes/js/course.js' ),array('jquery','wp-mediaelement','buddypress-js'),bp_course_version(),true);
	
	$color=bp_wplms_get_theme_color();
	$single_dark_color=bp_wplms_get_theme_single_dark_color();
	$translation_array = array( 
		'timeout' => _x( 'TIMEOUT','displayed to suer when quiz times out.','vibe' ), 
		'too_fast_answer' => _x( 'Too Fast or Answer not marked.','Quiz answer being marked very fast','vibe' ), 
		'answer_saved' => _x( 'Answer Saved.','Save answer on every question, confirmation message','vibe' ), 
		'processing' => _x( 'Processing...','Quiz question anwer save under progress','vibe' ), 
		'saving_answer' => _x( 'Saving Answer...please wait','Saving quiz answers under progress','vibe' ), 
		'remove_user_text' => __( 'This step is irreversible. Are you sure you want to remove the User from the course ?','vibe' ), 
		'remove_user_button' => __( 'Confirm, Remove User from Course','vibe' ), 
		'confirm' => _x( 'Confirm','Confirm button for various popup confirmation messages','vibe' ), 
		'cancel' => _x( 'Cancel','Cancel button for various popup confirmation messages','vibe' ), 
		'reset_user_text' => __( 'This step is irreversible. All Units, Quiz results would be reset for this user. Are you sure you want to Reset the Course for this User?','vibe' ), 
		'reset_user_button' => __( 'Confirm, Reset Course for this User','vibe' ), 
		'quiz_reset' => __( 'This step is irreversible. All Questions answers would be reset for this user. Are you sure you want to Reset the Quiz for this User? ','vibe' ), 
		'quiz_reset_button' => __( 'Confirm, Reset Quiz for this User','vibe' ), 
		'marks_saved' => __( 'Marks Saved','vibe' ), 
		'quiz_marks_saved' => __( 'Quiz Marks Saved','vibe' ), 
		'save_quiz' => __( 'Save Quiz progress','vibe' ), 
		'saved_quiz_progress' => __( 'Saved','vibe' ), 
		'submit_quiz' => __( 'Submit Quiz','vibe' ), 
		'sending_messages' => __( 'Sending Messages ...','vibe' ), 
		'adding_students' => __( 'Adding Students to Course ...','vibe' ), 
		'successfuly_added_students' => __( 'Students successfully added to Course','vibe' ),
		'unable_add_students' => __( 'Unable to Add students to Course','vibe' ),
		'select_fields' => __( 'Please select fields to download','vibe' ),
		'download' => __( 'Download','vibe' ),
		'timeout' => __( 'TIMEOUT','vibe' ),
		'theme_color' => $color,
		'single_dark_color' => $single_dark_color,
		'for_course' => __( 'for Course','vibe' ),
		'active_filters' => __( 'Active Filters','vibe' ),
		'clear_filters' => __( 'Clear all filters','vibe' ),
		'remove_comment' => __( 'Are you sure you want to remove this note?','vibe' ),
		'remove_comment_button' => __( 'Confirm, remove note','vibe' ), 
		'private_comment'=> __( 'Make Private','vibe' ), 
		'add_comment'=> __( 'Add your note','vibe' ), 
		'submit_quiz_error'=> __( 'Please add questions or retake the quiz !','vibe' ), 
		'remove_announcement'=> __( 'Are you sure you want to remove this Annoucement?','vibe' ), 
		'start_quiz_notification'=> __( 'You\'re about to start the Quiz. Please click confirm to begin the quiz.','vibe' ), 
		'submit_quiz_notification'=> __( 'Are you sure you want to submit the quiz. Submitting the quiz will freeze all your answers, you can not change them.  Please confirm.','vibe' ), 
		'check_results'=> __( 'Check results','vibe' ), 
		'correct'=> __( 'Correct','vibe' ), 
		'incorrect'=> __( 'Incorrect','vibe' ),
		'confirm_apply'=> _x('Are you sure you want to apply for this Course ?','confirmation message when user clicks on apply for course','vibe'),
		'instructor_uncomplete_unit' => _x('Are you sure you want mark this unit "incomplete" for the user ?','Popup confirmation message when instructor marks the unit uncomplete for the user.','vibe'),
		'instructor_complete_unit'=> _x('Are you sure you want to mark this unit "complete" for the user ?','Popup confirmation message ','vibe'),
		'unanswered_questions' => __( 'You have few unanswered questions. Are you sure you want to continue ?','vibe' ), 
		'enter_more_characters' => __( 'Please enter 4 or more characters ...','vibe' ),
		'correct_answer'=> __( 'Correct Answer','vibe' ), 
		'explanation'=> __( 'Explanation','vibe' ), 
		'And'=> __( 'and','vibe' ), 
		);
	wp_localize_script( 'bp-course-js', 'vibe_course_module_strings', $translation_array );
    	
}

add_action( 'wp_enqueue_scripts', 'bp_course_add_js');


add_action('admin_enqueue_scripts','bp_course_admin_scripts');
function bp_course_admin_scripts(){
	wp_enqueue_script( 'bp-graph-js', plugins_url( '/vibe-course-module/includes/js/jquery.flot.min.js' ) );
}
?>