<?php
/**
 * MailMint Integration.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Integrations;

use Mint\MRM\DataBase\Tables\AutomationMetaSchema;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMail\App\Internal\Automation\AutomationModel;

/**
 * MailMint Integration
 *
 * Handles the creation, updating, deletion, and status management of automations in MailMint.
 *
 * @package OhMyLMS\Integrations
 * @since 1.0.0
 */
class MailMint {

	/**
	 * The post ID associated with the automation.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * MailMint constructor
	 *
	 * Initializes the integration by setting the post ID if MailMint is active.
	 *
	 * @param int $post_id The ID of the post to associate with the automation.
	 */
	public function __construct( $post_id ) {
		if ( $this->is_active() ) {
			$this->post_id = $post_id;
		}
	}


	/**
	 * Retrieves all automations from MailMint based on provided filters.
	 *
	 * @param array  $ids An array of automation IDs to filter.
	 * @param string $order_by Column to order by.
	 * @param string $order_type Sorting order (ASC/DESC).
	 * @param int    $offset Offset for pagination.
	 * @param int    $limit Number of records per page.
	 * @param string $search Search query.
	 * @param string $status Automation status.
	 *
	 * @return array|null Returns an array of automation data or null on failure.
	 */
	public static function get_all( $ids, $order_by, $order_type, $offset = 0, $limit = 10, $search = '', $status = '' ) {
		global $wpdb;
		$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_meta_table = $wpdb->prefix . AutomationMetaSchema::$table_name;
		$search_terms          = null;
		$condition             = 'WHERE';

		// Search automation by name.
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "WHERE automation.name LIKE '%%$search%%'";
			$condition    = 'AND';
		}

		// Filter by IDs.
		if ( ! empty( $ids ) && is_array( $ids ) ) {
			$ids_placeholder = implode( ',', array_map( 'intval', $ids ) );
			$condition      .= " automation.id IN ($ids_placeholder) AND";
		}

		try {
			if ( 'all' === $status ) {
				$select_query = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT automation.id, automation.name, automation.status, automation.created_at 
                    FROM $automation_table as automation 
                    LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id 
                    {$search_terms} {$condition} meta.meta_key = %s AND meta.meta_value = %s 
                    ORDER BY automation.$order_by $order_type 
                    LIMIT %d, %d",
						array( 'source', 'ohmylms', $offset, $limit )
					),
					ARRAY_A
				);

				$count_query = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) 
                    FROM $automation_table as automation 
                    LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id 
                    {$search_terms} {$condition} meta.meta_key  = %s AND  meta.meta_value  = %s",
						array( 'source', 'ohmylms' )
					)
				);
			} else {
				$select_query = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT automation.id, automation.name, automation.status, automation.created_at 
                    FROM $automation_table as automation 
                    LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id 
                    {$search_terms} {$condition} meta.meta_key = %s AND meta.meta_value = %s 
                    AND automation.status = %s 
                    ORDER BY automation.$order_by $order_type 
                    LIMIT %d, %d",
						array( 'source', 'ohmylms', $status, $offset, $limit )
					),
					ARRAY_A
				);

				$count_query = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) 
                    FROM $automation_table as automation 
                    LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id 
                    {$search_terms} {$condition} meta.meta_key  = %s AND  meta.meta_value  = %s 
                    AND automation.status = %s",
						array( 'source', 'ohmylms', $status )
					)
				);
			}

			$count       = (int) $count_query;
			$total_pages = ceil( $count / $limit );
			$data        = array(
				'data'        => $select_query,
				'total_pages' => $total_pages,
				'count'       => $count,
			);
			if ( isset( $data['data'] ) ) {
				$data['data'] = array_map(
					function ( $automation ) {
						if ( is_array( $automation ) && ! empty( $automation ) ) {
							$created_at    = isset( $automation['created_at'] ) ? $automation['created_at'] : '';
							$automation_id = isset( $automation['id'] ) ? $automation['id'] : '';

                            $automation['created_ago'] = human_time_diff( strtotime( $created_at ), current_time( 'timestamp' ) ); //phpcs:disable
                            $automation['enterance']   = HelperFunctions::count_total_enterance( $automation_id );
                            $automation['completed']   = HelperFunctions::count_completed_automation( $automation_id );
                            $automation['processing']  = $automation['enterance'] - $automation['completed'];
                        }
                        return $automation;
                    },
                    $data['data']
                );
            }

            return $data;
        } catch ( \Exception $e ) {
            return null;
        }
    }
    

    /**
     * Creates or updates an automation in MailMint.
     *
     * @param array $data The data to create or update the automation.
     * @return void
     */
    public function create_or_update_automation( $data ) {
       
        // Check if MailMint is active before proceeding
        if ( ! $this->is_active() ) {
            return;
        }
        if( isset( $data['atMostDate'] ) ){
            unset( $data['atMostDate'] );
        }
        
        $post_id = isset( $data['post_id'] ) ? $data['post_id'] : '';

        // Remove unnecessary keys from the data array
        if( isset( $data['isImport'] ) && $data['isImport'] && $data['steps'] && is_array( $data['steps'] ) && !empty( $data['steps'] ) ){
            foreach ( $data['steps'] as $key => $step ) {
                if( isset( $step['settings']['message_data']['subject']) ) {
                    $data['steps'][ $key ]['settings']['message_data']['subject'] = $this->replace_subject_placeholders( $step['settings']['message_data']['subject'], $post_id );
                }

                if( isset( $step['settings']['message_data']['body']) ) {
                    $data['steps'][ $key ]['settings']['message_data']['body'] = $this->replace_content_placeholders( $step['settings']['message_data']['body'], $post_id );
                }

                if( isset( $step['settings']['message_data']['json_body']) ) {
                    $data['steps'][ $key ]['settings']['message_data']['json_body'] = $this->replace_content_placeholders( $step['settings']['message_data']['json_body'], $post_id );
                }

                if ( isset( $step['id'] ) && !empty( $step['automation_id'] ) ) {
                    unset( $data['steps'][ $key ]['id'] ); // Remove the 'id' key if it exists
                    unset( $data['steps'][ $key ]['automation_id'] ); // Remove the 'step_id' key if it exists
                }
                 $data['steps'][ $key ]['popover_type'] = isset( $step['type'] ) && 'trigger' === $step['type'] ? $step['type'] : 'steps';
            }
            unset( $data['isImport'] );
            
        }
        if( isset( $data['post_id'] ) ){
            unset( $data['post_id'] );
        }
        // Save automation in MailMint
        $automation_id = AutomationModel::get_instance()->create_or_update( $data );
        if ( $automation_id ) {
            $get_at_most_date = isset( $data['atMostDate'] ) ? $data['atMostDate'] : '';
            $stat             = !empty( $data['showAnalyticsStat'] ) ? $data['showAnalyticsStat'] : false;

            // Update automation meta in MailMint
            \MintMail\App\Internal\Automation\HelperFunctions::update_automation_meta( $automation_id, 'source', 'ohmylms' );
            \MintMail\App\Internal\Automation\HelperFunctions::update_automation_meta( $automation_id, 'enable_stats', $stat );
            \MintMail\App\Internal\Automation\HelperFunctions::update_automation_meta( $automation_id, '_at_most_date', maybe_serialize( $get_at_most_date ) );

            // Get existing automation IDs for this post
            $existing_automations = get_post_meta( $this->post_id, '_mm_automation_id', true );

            // Ensure it's an array
            if ( ! is_array( $existing_automations ) ) {
                $existing_automations = [];
            }

            // Add the new automation ID if it's not already in the array
            if ( ! in_array( $automation_id, $existing_automations ) ) {
                $existing_automations[] = $automation_id;
            }

            // Update post meta with the updated array
            update_post_meta( $this->post_id, '_mm_automation_id', $existing_automations );
            return $automation_id;
        }
    }

    /**
     * Replace subject placeholders in MailMint.
     * 
     * @param string $subject The subject string with placeholders.
     * @param array  $data    The data to replace placeholders.
     * @return string The subject with replaced placeholders.
     * 
     * @since 1.1.6
     * @param mixed $post_id Post identifier.
     */
    public function replace_subject_placeholders( $subject, $post_id ) {
        $placeholders = [
            '[course_name]' => '',
            '[lesson_name]'   => '',
            '[quiz_name]'     => '',
            '[assignment_name]' => '',
        ];

        // Get course name
        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $placeholders['[course_name]'] = $post->post_title;
                $placeholders['[lesson_name]'] = $post->post_title;
                $placeholders['[quiz_name]'] = $post->post_title;
                $placeholders['[assignment_name]'] = $post->post_title;
            }
        }

        // Replace placeholders in subject
        foreach ( $placeholders as $placeholder => $value ) {
            $subject = str_replace( $placeholder, $value, $subject );
        }

        return $subject;
        
    }


    /**
     * Replace content placeholders in MailMint.
     * 
     * @param string $content The content string with placeholders.
     * @param array  $data    The data to replace placeholders.
     * @return string The content with replaced placeholders.
     * @since 1.1.6
     * @param mixed $post_id Post identifier.
     */
    public function replace_content_placeholders( $content, $post_id ) {
        $placeholders = [
            '[course_name]' => '',
            '[lesson_name]'   => '',
            '[quiz_name]'     => '',
            '[assignment_name]' => '',
        ];

        // Get course name
        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $placeholders['[course_name]'] = $post->post_title;
                $placeholders['[lesson_name]'] = $post->post_title;
                $placeholders['[quiz_name]'] = $post->post_title;
                $placeholders['[assignment_name]'] = $post->post_title;
            }
        }

        // Replace placeholders in content
        foreach ( $placeholders as $placeholder => $value ) {
            $content = str_replace( $placeholder, $value, $content );
        }

        return $content;
    }


    /**
     * Updates the status of an automation in MailMint.
     *
     * @param int $automation_id The ID of the automation to update.
     * @param string $status The new status to set.
     * @return void
     */
    public function update_automation_status( $automation_id, $status ) {
        // Check if MailMint is active before proceeding
        if ( ! $this->is_active() ) {
            return;
        }

        // Update automation status in MailMint
        \MintMail\App\Internal\Automation\HelperFunctions::update_status( $automation_id, $status );
    }

    /**
     * Deletes an automation in MailMint.
     *
     * @param int $automation_id The ID of the automation to delete.
     * @return void
     */
    public function delete_automation( $automation_id ) {
        // Check if MailMint is active before proceeding
        if ( ! $this->is_active() ) {
            return;
        }

        // Delete automation in MailMint
        AutomationModel::destroy( $automation_id );

        // Get existing automation IDs
        $existing_automations = get_post_meta( $this->post_id, '_mm_automation_id', true );

        if ( is_array( $existing_automations ) ) {
            // Remove the deleted automation ID
            $updated_automations = array_diff( $existing_automations, [ $automation_id ] );

            if ( empty( $updated_automations ) ) {
                // If no automations left, delete the meta key
                delete_post_meta( $this->post_id, '_mm_automation_id' );
            } else {
                // Otherwise, update the post meta
                update_post_meta( $this->post_id, '_mm_automation_id', array_values( $updated_automations ) );
            }
        }
    }


    /**
     * Duplicate automation for Mail mint.
     *
     * @param mixed $automation_id Automation identifier.
     */
    public function duplicate_automation( $automation_id ){
        
        if ( ! $this->is_active() ) {
            return;
        }

        // Get the original automation.
		$original_automation = AutomationModel::get_single( $automation_id );
		if ( empty( $original_automation ) ) {
			return false;
		}

        $automations   = HelperFunctions::clone_automation( $original_automation, $automation_id );
        $new_automation_id = isset($automations['id']) ? $automations['id'] : '';

        if ( !$new_automation_id ) {
            return false;
        }
        \MintMail\App\Internal\Automation\HelperFunctions::update_automation_meta( $new_automation_id, 'source', 'ohmylms' );
        $step_data = [];

        foreach ($automations['steps'] as $key => $step) {
            $step_data = HelperFunctions::generate_individual_step_data($automations['steps'], $step_data, $key, $step, $new_automation_id);
        }

        HelperFunctions::create_duplicate_automation_steps($step_data, $new_automation_id);

        // Get existing automation IDs for this post
        $existing_automations = get_post_meta( $this->post_id, '_mm_automation_id', true );

        // Ensure it's an array
        if ( ! is_array( $existing_automations ) ) {
            $existing_automations = [];
        }

        // Add the new automation ID if it's not already in the array
        if ( ! in_array( $new_automation_id, $existing_automations ) ) {
            $existing_automations[] = $new_automation_id;
        }

        // Update post meta with the updated array
        update_post_meta( $this->post_id, '_mm_automation_id', $existing_automations );

        return $new_automation_id;
    }

    /**
     * Checks if MailMint integration is active.
     *
     * @return bool True if MailMint is active, false otherwise.
     */
    private function is_active() {
        return defined( 'MAILMINT' );
    }
}
?>
