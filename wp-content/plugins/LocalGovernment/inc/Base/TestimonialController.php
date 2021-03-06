<?php 
/**
 * @package  ibbhaber
 */
namespace Inc\Base;

use Inc\Api\SettingsApi;
use Inc\Base\BaseController;
use Inc\Api\Callbacks\AdminCallbacks;
use Inc\Api\Callbacks\TestimonialCallbacks;
/**
* 
*/
class TestimonialController extends BaseController
{
	public $settings;

	public $callbacks;

	public $subpages = array();

	public function register()
	{
		if ( ! $this->activated( 'testimonial_manager' ) ) return;

		$this->settings = new SettingsApi();
		$this->callbacks = new TestimonialCallbacks();

		add_action('init', array($this,'testimonial_cpt'));

		add_action('add_meta_boxes', array($this,'add_meta_boxes'));
		add_action('save_post', array($this,'save_meta_box'));
		add_action('manage_testimonial_posts_columns',array($this,'set_custom_columns'));
		add_action('manage_testimonial_posts_custom_column',array($this, 'set_custom_columns_data'),10, 2);
		add_filter('manage_edit-testimonial_sortable_columns', array($this, 'set_custom_columns_sortable'));

		$this->setShortcodePage();
		add_shortcode('testimonial-form', array($this, 'testimonial_form'));
		add_shortcode('testimonial-slideshow', array($this, 'testimonial_slideshow'));
		add_action('wp_ajax_submit_testimonial', array($this,'submit_testimonial'));
		add_action('wp_ajax_nopriv_submit_testimonial', array($this,'submit_testimonial'));

	}
	public function submit_testimonial(){
		if(! DOING_AJAX || ! check_ajax_referer('testimonial_nonce', 'nonce')){
			$this->return_json('error');
		}

		$name = sanitize_text_field($_POST['name']);
		$email = sanitize_email($_POST['email']);
		$message = sanitize_textarea_field($_POST['message']);

		$data = array( 
			'name' => $name,
			'email' =>$email,
			'approved' => 0,
			'featured' => 0,
		);

		$args = array(
			'post_title' => 'Testimonial from ' . $name,
			'post_content' => $message,
			'post_author' => 1,
			'post_status' => 'publish',
			'post_type' => 'testimonial',
			'meta_input' => array(
				'_ibbhaber_testimonial_key' => $data
			)
		);

		$postID = wp_insert_post($args);

		if($postID){
			$this->return_json('success');
		}

		$this->return_json('error');
	}

	public function return_json($status){
		$return = array(
			'status' => $status
		);
		wp_send_json($return);

		wp_die();
	}


	public function testimonial_form(){
		ob_start();
		echo "<link rel=\"stylesheet\" href=\"$this->plugin_url/assets/form.css\" type=\"text/css\" media=\"all\"/>";
		require_once("$this->plugin_path/templates/contact-form.php");
		echo "<script src=\"$this->plugin_url/src/js/form.js\"></script>";
		return ob_get_clean();
	}

	public function testimonial_slideshow(){
		ob_start();
		echo "<link rel=\"stylesheet\" href=\"$this->plugin_url/assets/slider.css\" type=\"text/css\" media=\"all\"/>";
		require_once("$this->plugin_path/templates/slider.php");
		echo "<script src=\"$this->plugin_url/src/js/slider.js\"></script>";
		return ob_get_clean();
	}

	public function setShortcodePage(){
		$subpage = array(
			array(
				'parent_slug' => 'edit.php?post_type=testimonial',
				'page_title' => 'Kısa Kodlar',
				'menu_title' => 'Shortcodes',
				'capability' => 'manage_options',
				'menu_slug' => 'ibbhaber_testimonial_shortcode',
				'callback' => array($this->callbacks, 'shortcodePage')
			)
		);

		$this->settings->addSubPages($subpage)->register();
	}


	public function testimonial_cpt(){

		$labels = array(
			'name' => 'Testimonials',
			'singular_name' => 'Testimonial'
		);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'has_archive' => false,
			'menu_icon' => 'dashicons-testimonial',
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'supports' => array('title', 'editor') 

		);
		register_post_type('testimonial', $args);
	}

	public function add_meta_boxes(){
		add_meta_box(
			'testimonial_author',
			'Author',
			array($this,'render_features_box'),
			'testimonial',
			'side',
			'default'
		);

	}

	public function render_features_box($post)
	{
		wp_nonce_field( 'ibbhaber_testimonial', 'ibbhaber_testimonial_nonce' );

		$data = get_post_meta( $post->ID, '_ibbhaber_testimonial_key', true );
		$name = isset($data['name']) ? $data['name'] : '';
		$email = isset($data['email']) ? $data['email'] : '';
		$approved = isset($data['approved']) ? $data['approved'] : false;
		$featured = isset($data['featured']) ? $data['featured'] : false;
		?>
		<p>
			<label class="meta-label" for="ibbhaber_testimonial_author">Author Name</label>
			<input type="text" id="ibbhaber_testimonial_author" name="ibbhaber_testimonial_author" class="widefat" value="<?php echo esc_attr( $name ); ?>">
		</p>
		<p>
			<label class="meta-label" for="alecaddd_testimonial_email">Author Email</label>
			<input type="email" id="ibbhaber_testimonial_email" name="ibbhaber_testimonial_email" class="widefat" value="<?php echo esc_attr( $email ); ?>">
		</p>
		<div class="meta-container">
			<label class="meta-label w-50 text-left" for="ibbhaber_testimonial_approved">Approved</label>
			<div class="text-right w-50 inline">
				<div class="ui-toggle inline"><input type="checkbox" id="ibbhaber_testimonial_approved" name="ibbhaber_testimonial_approved" value="1" <?php echo $approved ? 'checked' : ''; ?>>
					<label for="ibbhaber_testimonial_approved"><div></div></label>
				</div>
			</div>
		</div>
		<div class="meta-container">
			<label class="meta-label w-50 text-left" for="ibbhaber_testimonial_featured">Featured</label>
			<div class="text-right w-50 inline">
				<div class="ui-toggle inline"><input type="checkbox" id="ibbhaber_testimonial_featured" name="ibbhaber_testimonial_featured" value="1" <?php echo $featured ? 'checked' : ''; ?>>
					<label for="ibbhaber_testimonial_featured"><div></div></label>
				</div>
			</div>
		</div>
		<?php
	}



	public function render_author_box($post){
		wp_nonce_field('ibbhaber_testimonial_author','ibbhaber_testimonial_author_nonce');
		$value =get_post_meta($post->ID,'_ibbhaber_testimonial_author_key', true);
		?>
		<label for="ibbhaber_testimonial_author">Testimonial Author</label>
		<input type="text" id="ibbhaber_testimonial_author" name="ibbhaber_testimonial_author" value=<?php echo esc_attr($value); ?>>
		<?php
		
	}

	public function save_meta_box($post_id){
		if (! isset($_POST['ibbhaber_testimonial_nonce'])) {
			return $post_id;
		}
		$nonce = $_POST['ibbhaber_testimonial_nonce'];
		if (! wp_verify_nonce($nonce, 'ibbhaber_testimonial')) {
			return $post_id;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return $post_id;
		}

		if(! current_user_can('edit_post',$post_id)){
			return $post_id;
		}

		$data = array( 
			'name' => sanitize_text_field( $_POST['ibbhaber_testimonial_author'] ),
			'email' => sanitize_text_field( $_POST['ibbhaber_testimonial_email'] ),
			'approved' => isset($_POST['ibbhaber_testimonial_approved']) ? 1 : 0,
			'featured' => isset($_POST['ibbhaber_testimonial_featured']) ? 1 : 0,
		);
		update_post_meta($post_id, '_ibbhaber_testimonial_key',$data);

	}

	public function set_custom_columns($columns){
		$title = $columns['title'];
		$date = $columns['date'];
		unset($columns['title'], $columns['date']);
		$columns['name'] = 'Author Name';
		$columns['title'] = $title;
		$columns['approved'] = 'Approved';
		$columns['featured'] = 'Featured';
		$columns['date'] = $date;
		return $columns;
	}

	public function set_custom_columns_data($column, $post_id){
		$data = get_post_meta( $post_id, '_ibbhaber_testimonial_key', true );
		$name = isset($data['name']) ? $data['name'] : '';
		$email = isset($data['email']) ? $data['email'] : '';
		$approved = isset($data['approved']) && $data['approved'] === 1 ? '<strong>YES</strong>' : 'NO';
		$featured = isset($data['featured']) && $data['featured'] === 1 ? '<strong>YES</strong>' : 'NO';

		switch ($column) {
			case 'name':
				echo '<strong>'. $name .'</strong><br/><a href="mailto:'. $email .'">'. $email .'</a>';
				break;
			case 'approved':
				echo $approved;
				break;
			case 'featured':
				echo $featured;
				break;
		}
	}

	public function set_custom_columns_sortable($columns){
		$columns['name'] = 'name';
		$columns['approved'] = 'approved';
		$columns['featured'] = 'featured';

		return $columns;
	}
}