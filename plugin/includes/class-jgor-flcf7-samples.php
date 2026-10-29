<?php
/**
 * Module 6: the sample page with forms that show the options of the plugin.
 *
 * Created on request from the box on the settings screen or from the notice
 * after the activation (both handled here through admin-post.php), removed
 * from the same box or by uninstall.php. The page is
 * private, so a live site does not expose the test forms, and every sample
 * form carries "skip_mail: on". The ids of the page and of the forms live in
 * the option jgor_flcf7_samples, which exists only while the samples do and
 * is the only source for removing them: nothing else is ever deleted.
 *
 * The sample forms are named "FL Test <name> - may be deleted". The first
 * one is the default form of Contact Form 7 (label mode), the others show
 * placeholder mode, text mode for old templates, the variants and focus
 * effects through additional settings, the limits of the rebuild and a form
 * with floating labels switched off. Two placements of the default form at
 * the end carry classes and design values of their own (block attributes
 * and shortcode attributes).
 *
 * @package Floating_Labels_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and removes the sample page and its forms.
 */
final class JGOR_FLCF7_Samples {

	/**
	 * Option with the ids of the sample page and forms (not autoloaded).
	 */
	public const OPTION = 'jgor_flcf7_samples';

	/**
	 * Transient set on activation; while it exists the admin notice asks
	 * whether to create the sample page.
	 */
	public const TRANSIENT = 'jgor_flcf7_activated';

	/**
	 * Slug of the sample page.
	 */
	public const PAGE_SLUG = 'testpage-floating-labels-for-cf7';

	/**
	 * Prefix of every sample form title.
	 */
	public const TITLE_PREFIX = 'FL Test ';

	/**
	 * Suffix of every sample form title.
	 */
	public const TITLE_SUFFIX = ' - may be deleted';

	/**
	 * Post type of Contact Form 7 forms.
	 */
	public const FORM_POST_TYPE = 'wpcf7_contact_form';

	/**
	 * Name of the admin-post action and of its nonce.
	 */
	public const ACTION = 'jgor_flcf7_samples';

	/**
	 * Query argument that carries the result back to the settings screen.
	 */
	public const RESULT_ARG = 'jgor_flcf7_samples';

	/**
	 * Post types the module may delete: only its own page and forms.
	 *
	 * @var string[]
	 */
	private const DELETABLE_TYPES = array( 'page', self::FORM_POST_TYPE );

	/**
	 * Classes of the first placement example (block attribute htmlClass).
	 */
	private const PLACEMENT_CLASSES = 'jgor-flcf7-style-filled jgor-flcf7-effect-glow';

	/**
	 * Design values of both placement examples (property => value).
	 *
	 * @var array<string, string>
	 */
	private const PLACEMENT_DESIGN = array(
		'border-color' => '#0a7d3e',
		'font-size'    => '1.1em',
	);

	/**
	 * Returns the stored ids, with zero and an empty list when the option
	 * is missing or malformed.
	 *
	 * @return array{page: int, forms: int[]}
	 */
	public function stored(): array {
		$option = get_option( self::OPTION, array() );
		$option = is_array( $option ) ? $option : array();

		$forms = array();
		if ( isset( $option['forms'] ) && is_array( $option['forms'] ) ) {
			foreach ( $option['forms'] as $id ) {
				if ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) {
					$forms[] = (int) $id;
				}
			}
		}

		return array(
			'page'  => isset( $option['page'] ) && is_numeric( $option['page'] ) ? (int) $option['page'] : 0,
			'forms' => $forms,
		);
	}

	/**
	 * Returns the id of the sample page, or 0 while it does not exist (never
	 * created, or deleted or trashed by hand).
	 *
	 * @return int
	 */
	public function page_id(): int {
		$page = $this->stored()['page'];

		return $this->is_live( $page, 'page' ) ? $page : 0;
	}

	/**
	 * Whether the sample page exists.
	 *
	 * @return bool
	 */
	public function is_created(): bool {
		return $this->page_id() > 0;
	}

	/**
	 * Creates the sample forms and the page.
	 *
	 * Leftovers of a page that was deleted by hand (its forms, a trashed
	 * page) are removed first. When a form or the page cannot be saved,
	 * everything created so far is deleted again.
	 *
	 * @return string "created", "exists" (page already there), "missing"
	 *                (Contact Form 7 not loaded) or "failed".
	 */
	public function create(): string {
		if ( $this->is_created() ) {
			return 'exists';
		}

		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return 'missing';
		}

		$stored = $this->stored();
		$this->delete_posts( array_merge( array( $stored['page'] ), $stored['forms'] ) );
		delete_option( self::OPTION );

		$forms = array();
		$ids   = array();

		foreach ( $this->definitions() as $key => $definition ) {
			$form = $this->create_form( $definition['name'], $definition['template'], $definition['settings'] );
			if ( null === $form ) {
				$this->delete_posts( $ids );
				return 'failed';
			}

			$forms[ $key ] = $form;
			$ids[]         = (int) $form->id();
		}

		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'private',
				'post_title'     => __( 'Testpage Floating Labels for CF7', 'floating-labels-for-cf7' ),
				'post_name'      => self::PAGE_SLUG,
				'post_content'   => $this->page_content( $forms ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) || 0 === $page_id ) {
			$this->delete_posts( $ids );
			return 'failed';
		}

		update_option(
			self::OPTION,
			array(
				'page'  => (int) $page_id,
				'forms' => $ids,
			),
			false
		);

		return 'created';
	}

	/**
	 * Deletes the sample page and forms permanently and drops the option.
	 *
	 * @return string "removed", or "absent" when nothing was stored.
	 */
	public function remove(): string {
		if ( false === get_option( self::OPTION, false ) ) {
			return 'absent';
		}

		$stored = $this->stored();
		$this->delete_posts( array_merge( array( $stored['page'] ), $stored['forms'] ) );
		delete_option( self::OPTION );

		return 'removed';
	}

	/**
	 * Registers the hooks: the admin-post handler of the box and the notice,
	 * the box on the settings screen (action jgor_flcf7_settings_page_top of
	 * module 4) and the notice after the activation.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'jgor_flcf7_settings_page_top', array( $this, 'render_box' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Handles the admin-post request of the box and of the notice.
	 *
	 * Nonce first, then the capabilities of the action (perform()); the
	 * result travels back to the settings screen as a query argument that
	 * module 4 turns into a notice. "dismiss" only drops the transient and
	 * returns to the page the notice was shown on.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'floating-labels-for-cf7' ), 403 );
		}

		$task = '';
		if ( isset( $_POST['do'] ) ) {
			$task = sanitize_key( wp_unslash( $_POST['do'] ) );
		} elseif ( isset( $_GET['do'] ) ) {
			$task = sanitize_key( wp_unslash( $_GET['do'] ) );
		}

		$result = $this->perform( $task );
		if ( 'unknown' === $result ) {
			wp_die( esc_html__( 'Unknown action.', 'floating-labels-for-cf7' ), 400 );
		}

		if ( 'dismissed' === $result ) {
			$back = wp_get_referer();
			wp_safe_redirect( is_string( $back ) && '' !== $back ? $back : admin_url() );
		} else {
			wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result, $this->settings_url() ) );
		}

		// The redirect is on its way; the request has to end here.
		exit;
	}

	/**
	 * Runs one action after checking its capabilities and returns the result.
	 *
	 * Creating needs the Contact Form 7 capability to edit forms and the
	 * right to publish pages, removing the capability to delete forms and
	 * the right to delete pages. Both drop the transient of the notice.
	 *
	 * @param string $task "create", "remove" or "dismiss".
	 * @return string created|exists|missing|failed|removed|absent|dismissed|unknown
	 */
	public function perform( string $task ): string {
		switch ( $task ) {
			case 'create':
				if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) || ! current_user_can( 'publish_pages' ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to create contact forms and pages.', 'floating-labels-for-cf7' ), 403 );
				}
				delete_transient( self::TRANSIENT );
				return $this->create();

			case 'remove':
				if ( ! current_user_can( 'wpcf7_delete_contact_forms' ) || ! current_user_can( 'delete_pages' ) ) {
					wp_die( esc_html__( 'Sorry, you are not allowed to delete contact forms and pages.', 'floating-labels-for-cf7' ), 403 );
				}
				delete_transient( self::TRANSIENT );
				return $this->remove();

			case 'dismiss':
				delete_transient( self::TRANSIENT );
				return 'dismissed';
		}

		return 'unknown';
	}

	/**
	 * Prints the box "Sample page" on the settings screen.
	 *
	 * @return void
	 */
	public function render_box(): void {
		$page_id = $this->page_id();
		?>
		<div class="card jgor-flcf7-samples">
			<h2 class="jgor-flcf7-samples__title"><?php esc_html_e( 'Sample page', 'floating-labels-for-cf7' ); ?></h2>
			<p><?php esc_html_e( 'A private page "Testpage Floating Labels for CF7" with forms that show the options: the default form of Contact Form 7, placeholders, an old template, the variants and focus effects, the limits of the rebuild, a form switched off, and two placements with settings of their own. The forms are named "FL Test ... - may be deleted" and send no mail. Only logged-in users who may read private pages see the page.', 'floating-labels-for-cf7' ); ?></p>
			<?php if ( ! class_exists( 'WPCF7_ContactForm' ) ) : ?>
				<p><em><?php esc_html_e( 'Contact Form 7 is not active.', 'floating-labels-for-cf7' ); ?></em></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php if ( $page_id > 0 ) : ?>
						<p>
							<?php esc_html_e( 'The sample page exists.', 'floating-labels-for-cf7' ); ?>
							<a href="<?php echo esc_url( (string) get_permalink( $page_id ) ); ?>"><?php esc_html_e( 'View page', 'floating-labels-for-cf7' ); ?></a>
							&middot;
							<a href="<?php echo esc_url( (string) get_edit_post_link( $page_id, 'raw' ) ); ?>"><?php esc_html_e( 'Edit page', 'floating-labels-for-cf7' ); ?></a>
						</p>
						<input type="hidden" name="do" value="remove">
						<?php submit_button( __( 'Remove sample page and forms', 'floating-labels-for-cf7' ), 'secondary', 'jgor-flcf7-samples-remove', false ); ?>
					<?php else : ?>
						<p><?php esc_html_e( 'The sample page does not exist yet.', 'floating-labels-for-cf7' ); ?></p>
						<input type="hidden" name="do" value="create">
						<?php submit_button( __( 'Create sample page and forms', 'floating-labels-for-cf7' ), 'primary', 'jgor-flcf7-samples-create', false ); ?>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prints the notice after the activation: while the transient exists,
	 * for users who manage options, with Contact Form 7 active and no sample
	 * page yet.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( false === get_transient( self::TRANSIENT ) || ! current_user_can( 'manage_options' ) || ! class_exists( 'WPCF7_ContactForm' ) || $this->is_created() ) {
			return;
		}

		$create  = $this->action_url( 'create' );
		$dismiss = $this->action_url( 'dismiss' );

		wp_admin_notice(
			'<strong>' . esc_html__( 'Floating Labels for CF7', 'floating-labels-for-cf7' ) . '</strong> '
			. esc_html__( 'Create a private sample page with forms that show the options? You can remove it again at any time under Contact > Floating Labels.', 'floating-labels-for-cf7' )
			. ' <a class="button button-primary" href="' . esc_url( $create ) . '">' . esc_html__( 'Create sample page', 'floating-labels-for-cf7' ) . '</a> '
			. '<a class="button" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Not now', 'floating-labels-for-cf7' ) . '</a>',
			array(
				'type'               => 'info',
				'additional_classes' => array( 'jgor-flcf7-notice' ),
				'paragraph_wrap'     => true,
			)
		);
	}

	/**
	 * Builds the admin-post URL of one action, with the nonce.
	 *
	 * @param string $task "create" or "dismiss".
	 * @return string
	 */
	private function action_url( string $task ): string {
		$url = add_query_arg(
			array(
				'action' => self::ACTION,
				'do'     => $task,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, self::ACTION );
	}

	/**
	 * Returns the URL of the settings screen of module 4.
	 *
	 * @return string
	 */
	private function settings_url(): string {
		$url = menu_page_url( JGOR_FLCF7_Admin::PAGE_SLUG, false );

		return '' === $url ? admin_url( 'admin.php?page=' . JGOR_FLCF7_Admin::PAGE_SLUG ) : $url;
	}

	/**
	 * Whether a post exists with the given type and is not in the trash.
	 *
	 * @param int    $id   Post id.
	 * @param string $type Post type.
	 * @return bool
	 */
	private function is_live( int $id, string $type ): bool {
		if ( $id <= 0 ) {
			return false;
		}

		$post = get_post( $id );

		return $post instanceof WP_Post && $type === $post->post_type && 'trash' !== $post->post_status;
	}

	/**
	 * Deletes posts permanently, but only pages and contact forms.
	 *
	 * @param int[] $ids Post ids; unknown ids are skipped.
	 * @return void
	 */
	private function delete_posts( array $ids ): void {
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}

			$post = get_post( $id );
			if ( $post instanceof WP_Post && in_array( $post->post_type, self::DELETABLE_TYPES, true ) ) {
				wp_delete_post( $id, true );
			}
		}
	}

	/**
	 * Creates and saves one sample form from the Contact Form 7 template.
	 *
	 * @param string      $name     Name between prefix and suffix of the title.
	 * @param string|null $template Form template, null keeps the Contact Form 7 default.
	 * @param string      $settings Additional settings.
	 * @return WPCF7_ContactForm|null The saved form, or null when saving failed.
	 */
	private function create_form( string $name, ?string $template, string $settings ): ?WPCF7_ContactForm {
		$form = WPCF7_ContactForm::get_template(
			array(
				'title' => self::TITLE_PREFIX . $name . self::TITLE_SUFFIX,
			)
		);

		$properties = array( 'additional_settings' => $settings );
		if ( null !== $template ) {
			$properties['form'] = $template;
		}
		$form->set_properties( $properties );

		$id = $form->save();
		if ( ! is_int( $id ) || $id <= 0 ) {
			return null;
		}

		// Reloaded from the post: the saved object does not know the hash
		// Contact Form 7 wrote as post meta.
		$saved = WPCF7_ContactForm::get_instance( $id );

		return $saved instanceof WPCF7_ContactForm ? $saved : null;
	}

	/**
	 * The sample forms in the order of the page: key => name (title part),
	 * template (null for the Contact Form 7 default), additional settings,
	 * heading and explanation.
	 *
	 * @return array<string, array{name: string, template: string|null, settings: string, heading: string, text: string}>
	 */
	private function definitions(): array {
		$placeholders = "[text* your-name placeholder \"Your name\"]\n\n"
			. "[email* your-email placeholder \"Your email\"]\n\n"
			. "[textarea your-message placeholder \"Your message\"]\n\n"
			. '[submit "Send"]';

		$old_template = "<p>Your Name (required)<br />\n    [text* your-name] </p>\n\n"
			. "<p>Your Email (required)<br />\n    [email* your-email] </p>\n\n"
			. "<p>Subject<br />\n    [text your-subject] </p>\n\n"
			. "<p>Your Message<br />\n    [textarea your-message] </p>\n\n"
			. '<p>[submit "Send"]</p>';

		$mixed = "<label for=\"fl-sample-subject\">Subject</label>\n[text* your-subject id:fl-sample-subject]\n\n"
			. "[text your-nick nofloat placeholder \"Nickname (nofloat)\"]\n\n"
			. "<label> Your <strong>website</strong>\n    [url your-site] </label>\n\n"
			. "[tel your-phone placeholder \"Phone\"]\n\n"
			. "[number your-age min:0 max:120 placeholder \"Age\"]\n\n"
			. "[select your-choice \"One\" \"Two\"]\n\n"
			. "[checkbox your-cb \"A\" \"B\"]\n\n"
			. "[file your-file]\n\n"
			. '[submit "Send"]';

		return array(
			'default'      => array(
				'name'     => 'Default template',
				'template' => null,
				'settings' => 'skip_mail: on',
				'heading'  => __( 'Default template', 'floating-labels-for-cf7' ),
				'text'     => __( 'The form Contact Form 7 creates by default: the labels of the template become floating labels (label mode). Variant and focus effect follow the settings screen.', 'floating-labels-for-cf7' ),
			),
			'placeholders' => array(
				'name'     => 'Placeholders',
				'template' => $placeholders,
				'settings' => 'skip_mail: on',
				'heading'  => __( 'Placeholders', 'floating-labels-for-cf7' ),
				'text'     => __( 'Fields with a placeholder option only: the placeholder becomes the floating label (placeholder mode).', 'floating-labels-for-cf7' ),
			),
			'old-template' => array(
				'name'     => 'Old template',
				'template' => $old_template,
				'settings' => "skip_mail: on\nfloating_labels: text",
				'heading'  => __( 'Old template: text before the field', 'floating-labels-for-cf7' ),
				'text'     => __( 'A form as Contact Form 7 created it before version 5.4, with loose text in front of the fields instead of labels or placeholders. The additional setting "floating_labels: text" switches text mode on for this form.', 'floating-labels-for-cf7' ),
			),
			'filled'       => array(
				'name'     => 'Filled and glow',
				'template' => null,
				'settings' => "skip_mail: on\nfloating_labels_style: filled\nfloating_labels_effect: glow",
				'heading'  => __( 'Filled variant with glow', 'floating-labels-for-cf7' ),
				'text'     => __( 'The default template with "floating_labels_style: filled" and "floating_labels_effect: glow" in its additional settings.', 'floating-labels-for-cf7' ),
			),
			'minimal'      => array(
				'name'     => 'Minimal and running light',
				'template' => $placeholders,
				'settings' => "skip_mail: on\nfloating_labels_style: minimal\nfloating_labels_effect: sweep\nfloating_labels_marker: off",
				'heading'  => __( 'Minimal variant with running light', 'floating-labels-for-cf7' ),
				'text'     => __( 'The placeholder form with "floating_labels_style: minimal", "floating_labels_effect: sweep" and "floating_labels_marker: off": your theme styles the fields, a light runs around the focused field, and required fields get no asterisk.', 'floating-labels-for-cf7' ),
			),
			'mixed'        => array(
				'name'     => 'Mixed',
				'template' => $mixed,
				'settings' => "skip_mail: on\nfloating_labels_effect: pulse",
				'heading'  => __( 'Mixed form with pulsing glow', 'floating-labels-for-cf7' ),
				'text'     => __( 'A linked label, a field with the option nofloat, inline markup inside a label, telephone and number fields, and a select, checkboxes and a file upload that keep their original markup; "floating_labels_effect: pulse" in the additional settings.', 'floating-labels-for-cf7' ),
			),
			'off'          => array(
				'name'     => 'Off',
				'template' => $placeholders,
				'settings' => "skip_mail: on\nfloating_labels: off",
				'heading'  => __( 'Switched off', 'floating-labels-for-cf7' ),
				'text'     => __( 'The placeholder form with "floating_labels: off": rendered exactly as Contact Form 7 renders it.', 'floating-labels-for-cf7' ),
			),
		);
	}

	/**
	 * Builds the block content of the sample page.
	 *
	 * @param array<string, WPCF7_ContactForm> $forms Saved forms keyed as in definitions().
	 * @return string Block markup.
	 */
	private function page_content( array $forms ): string {
		$blocks = array(
			$this->paragraph_block( __( 'This page was created by Floating Labels for CF7 to show its options. It is private. You can remove it together with the forms "FL Test ... - may be deleted" at any time under Contact > Floating Labels.', 'floating-labels-for-cf7' ) ),
		);

		foreach ( $this->definitions() as $key => $definition ) {
			if ( ! isset( $forms[ $key ] ) ) {
				continue;
			}

			$blocks[] = $this->heading_block( $definition['heading'] );
			$blocks[] = $this->paragraph_block( $definition['text'] );

			// The old template next to the one Contact Form 7 writes today.
			if ( 'old-template' === $key ) {
				$blocks[] = $this->paragraph_block( __( 'Template of this form, as Contact Form 7 created it before version 5.4:', 'floating-labels-for-cf7' ) );
				$blocks[] = $this->code_block( "<p>Your Name (required)<br />\n    [text* your-name] </p>" );
				$blocks[] = $this->paragraph_block( __( 'The same field as Contact Form 7 writes it today (label mode):', 'floating-labels-for-cf7' ) );
				$blocks[] = $this->code_block( "<label> Your name\n    [text* your-name autocomplete:name] </label>" );
			}

			$blocks[] = $this->form_block( $forms[ $key ] );
		}

		if ( isset( $forms['default'] ) ) {
			$blocks[] = $this->heading_block( __( 'Per placement: block settings', 'floating-labels-for-cf7' ) );
			$blocks[] = $this->paragraph_block( __( 'The default form once more, with the panel "Floating labels" of the block set to Filled and Glow and with a border colour and font size from the design panels. Both are stored with this block only; select it to see the panels in the sidebar.', 'floating-labels-for-cf7' ) );
			$blocks[] = $this->form_block( $forms['default'], self::PLACEMENT_CLASSES, self::PLACEMENT_DESIGN );

			$blocks[] = $this->heading_block( __( 'Per placement: shortcode attributes', 'floating-labels-for-cf7' ) );
			$blocks[] = $this->paragraph_block( __( 'The same form in a Shortcode block, with the attributes html_class and jgor_flcf7_design written by hand.', 'floating-labels-for-cf7' ) );
			$blocks[] = $this->shortcode_block( $forms['default'], 'jgor-flcf7-style-minimal', self::PLACEMENT_DESIGN );
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Builds a heading block (h2).
	 *
	 * @param string $text Heading text.
	 * @return string
	 */
	private function heading_block( string $text ): string {
		return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->";
	}

	/**
	 * Builds a paragraph block.
	 *
	 * @param string $text Paragraph text.
	 * @return string
	 */
	private function paragraph_block( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * Builds a code block, the code escaped as the editor stores it.
	 *
	 * @param string $code Code as plain text.
	 * @return string
	 */
	private function code_block( string $code ): string {
		return "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>" . esc_html( $code ) . "</code></pre>\n<!-- /wp:code -->";
	}

	/**
	 * Builds a Contact Form 7 block exactly as its editor saves it: the
	 * attributes id, hash and title (plus htmlClass and the design values of
	 * module 5), and the shortcode with the hash as id inside the wrapper.
	 *
	 * @param WPCF7_ContactForm     $form       Saved form.
	 * @param string                $html_class Classes for the html_class attribute, '' for none.
	 * @param array<string, string> $design     Design values for the block attribute jgorFlcf7Design.
	 * @return string
	 */
	private function form_block( WPCF7_ContactForm $form, string $html_class = '', array $design = array() ): string {
		$attributes = array(
			'id'    => (int) $form->id(),
			'hash'  => $form->hash(),
			'title' => $form->title(),
		);

		if ( '' !== $html_class ) {
			$attributes['htmlClass'] = $html_class;
		}
		if ( ! empty( $design ) ) {
			$attributes[ JGOR_FLCF7_Placement_Design::BLOCK_ATTRIBUTE ] = $design;
		}

		return '<!-- wp:contact-form-7/contact-form-selector ' . serialize_block_attributes( $attributes ) . " -->\n"
			. '<div class="wp-block-contact-form-7-contact-form-selector">' . $this->shortcode( $form, $html_class, array() ) . "</div>\n"
			. '<!-- /wp:contact-form-7/contact-form-selector -->';
	}

	/**
	 * Builds a Shortcode block with the Contact Form 7 shortcode and the
	 * attributes html_class and jgor_flcf7_design.
	 *
	 * @param WPCF7_ContactForm     $form       Saved form.
	 * @param string                $html_class Classes for the html_class attribute.
	 * @param array<string, string> $design     Design values for the jgor_flcf7_design attribute.
	 * @return string
	 */
	private function shortcode_block( WPCF7_ContactForm $form, string $html_class, array $design ): string {
		return "<!-- wp:shortcode -->\n" . $this->shortcode( $form, $html_class, $design ) . "\n<!-- /wp:shortcode -->";
	}

	/**
	 * Builds the [contact-form-7] shortcode of a form.
	 *
	 * @param WPCF7_ContactForm     $form       Saved form.
	 * @param string                $html_class Classes for html_class, '' for none.
	 * @param array<string, string> $design     Design values for jgor_flcf7_design, empty for none.
	 * @return string
	 */
	private function shortcode( WPCF7_ContactForm $form, string $html_class, array $design ): string {
		$shortcode = '[contact-form-7 id="' . $form->hash() . '" title="' . esc_attr( $form->title() ) . '"';

		if ( '' !== $html_class ) {
			$shortcode .= ' html_class="' . esc_attr( $html_class ) . '"';
		}
		if ( ! empty( $design ) ) {
			$shortcode .= ' ' . JGOR_FLCF7_Placement_Design::SHORTCODE_ATTRIBUTE . '="' . esc_attr( JGOR_FLCF7_Placement_Design::serialize( $design ) ) . '"';
		}

		return $shortcode . ']';
	}
}
