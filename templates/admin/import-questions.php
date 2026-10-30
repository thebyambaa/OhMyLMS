<?php
/**
 * Import questions: paste or upload rows, check them, then import.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

$hub_status = array(
	'create'    => __( 'Will create', 'ohmylms' ),
	'update'    => __( 'Will update', 'ohmylms' ),
	'unchanged' => __( 'No change', 'ohmylms' ),
	'error'     => __( 'Error', 'ohmylms' ),
);
if ( $result && ! $result['dry_run'] ) {
	$hub_status['create'] = __( 'Created', 'ohmylms' );
	$hub_status['update'] = __( 'Updated', 'ohmylms' );
}
?>
<div class="ohmylms-admin-section ohmylms-import-questions">
	<h2><?php esc_html_e( 'Import questions', 'ohmylms' ); ?></h2>
	<p><?php esc_html_e( 'Add past papers or any other questions to the question bank. Every row is checked first, and nothing is written until you choose Import. Uploading the same rows again updates questions instead of duplicating them.', 'ohmylms' ); ?></p>

	<?php if ( $reviewed ) : ?>
		<?php
		$hub_done = count(
			array_filter(
				array_column( $reviewed, 'status' ),
				static function ( $status ) {
					return in_array( $status, array( 'approved', 'dismissed' ), true );
				}
			)
		);
		?>
		<div class="notice notice-success"><p><?php /* translators: %d: number of questions. */ echo esc_html( sprintf( _n( '%d question reviewed.', '%d questions reviewed.', $hub_done, 'ohmylms' ), $hub_done ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $review['total'] ) ) : ?>
		<h2><?php /* translators: %d: number of questions waiting. */ echo esc_html( sprintf( __( 'Waiting for review (%d)', 'ohmylms' ), $review['total'] ) ); ?></h2>
		<p><?php esc_html_e( 'These questions were classified by AI. They do not count toward practice or learner progress until you approve them. Check that each question is correct and that its skills fit, starting with the least confident.', 'ohmylms' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'ohmylms_review_questions' ); ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.oml-review-box').forEach(function(b){b.checked=this.checked}.bind(this))" aria-label="<?php esc_attr_e( 'Select all', 'ohmylms' ); ?>"></td><th><?php esc_html_e( 'Question', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Skills', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Level', 'ohmylms' ); ?></th><th><?php esc_html_e( 'AI confidence', 'ohmylms' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $review['items'] as $hub_item ) : ?>
						<tr>
							<th scope="row" class="check-column"><input class="oml-review-box" type="checkbox" name="review_ids[]" value="<?php echo esc_attr( $hub_item['id'] ); ?>" aria-label="<?php /* translators: %d: question ID. */ echo esc_attr( sprintf( __( 'Select question %d', 'ohmylms' ), $hub_item['id'] ) ); ?>"></th>
							<td>
								<strong><?php echo esc_html( $hub_item['name'] ); ?></strong> <small>#<?php echo esc_html( $hub_item['id'] ); ?> · <?php echo esc_html( $hub_item['type'] ); ?></small>
								<div><?php echo esc_html( $hub_item['text'] ); ?></div>
								<?php if ( $hub_item['notes'] ) : ?>
									<div style="color:#50575e"><em><?php echo esc_html( $hub_item['notes'] ); ?></em></div>
								<?php endif; ?>
							</td>
							<td>
								<?php foreach ( $hub_item['skills'] as $hub_skill ) : ?>
									<div><?php echo esc_html( ( 'primary' === $hub_skill['role'] ? '' : '+ ' ) . $hub_skill['code'] . ' ' . $hub_skill['name'] ); ?></div>
								<?php endforeach; ?>
							</td>
							<td><?php echo esc_html( $hub_item['difficulty'] ); ?><?php echo $hub_item['family_id'] ? '<br><small>' . esc_html( $hub_item['family_id'] ) . '</small>' : ''; ?></td>
							<td><?php echo null === $hub_item['confidence'] ? '-' : esc_html( round( $hub_item['confidence'] * 100 ) . '%' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="submit" name="ohmylms_review_decision" value="approve" class="button button-primary"><?php esc_html_e( 'Approve selected', 'ohmylms' ); ?></button>
				<button type="submit" name="ohmylms_review_decision" value="dismiss" class="button"><?php esc_html_e( 'Keep as draft (remove from this list)', 'ohmylms' ); ?></button>
			</p>
		</form>
		<hr>
	<?php endif; ?>

	<?php if ( $message ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>

	<?php if ( $result ) : ?>
		<?php $hub_counts = $result['counts']; ?>
		<div class="notice <?php echo $hub_counts['error'] ? 'notice-warning' : 'notice-success'; ?>">
			<p>
				<strong>
					<?php
					if ( $result['committed'] ) {
						esc_html_e( 'Import finished.', 'ohmylms' );
					} elseif ( ! empty( $result['stopped'] ) ) {
						esc_html_e( 'Nothing was imported.', 'ohmylms' );
					} else {
						esc_html_e( 'Check finished. Nothing has been imported yet.', 'ohmylms' );
					}
					?>
				</strong>
				<?php /* translators: 1: new questions, 2: updated, 3: unchanged, 4: rows with errors. */ ?>
				<?php echo esc_html( sprintf( __( '%1$d new, %2$d updated, %3$d unchanged, %4$d with errors.', 'ohmylms' ), $hub_counts['create'], $hub_counts['update'], $hub_counts['unchanged'], $hub_counts['error'] ) ); ?>
				<?php if ( $result['approved'] ) : ?>
					<?php /* translators: %d: number of questions approved. */ ?>
					<?php echo esc_html( sprintf( _n( '%d question approved.', '%d questions approved.', $result['approved'], 'ohmylms' ), $result['approved'] ) ); ?>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $result['stopped'] ) ) : ?>
				<p><?php echo esc_html( $result['stopped'] ); ?></p>
			<?php endif; ?>
		</div>
		<table class="widefat striped" style="max-width:1100px;margin-bottom:24px">
			<thead><tr><th><?php esc_html_e( 'Row', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Question', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Result', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Details', 'ohmylms' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $result['rows'] as $hub_row ) : ?>
					<tr>
						<td><?php echo esc_html( $hub_row['row'] ); ?></td>
						<td><?php echo esc_html( $hub_row['key'] ? $hub_row['key'] : '-' ); ?><?php echo $hub_row['id'] ? ' <small>#' . esc_html( $hub_row['id'] ) . '</small>' : ''; ?></td>
						<td><strong><?php echo esc_html( $hub_status[ $hub_row['status'] ] ); ?></strong></td>
						<td>
							<?php foreach ( $hub_row['errors'] as $hub_text ) : ?>
								<div style="color:#b32d2e"><?php echo esc_html( $hub_text ); ?></div>
							<?php endforeach; ?>
							<?php foreach ( $hub_row['warnings'] as $hub_text ) : ?>
								<div style="color:#8a6100"><?php echo esc_html( $hub_text ); ?></div>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'ohmylms_import_questions' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="oml-import-text"><?php esc_html_e( 'Rows', 'ohmylms' ); ?></label></th>
				<td>
					<textarea id="oml-import-text" name="text" rows="10" class="large-text code" placeholder="exam,paper,year,section,number,type,question,answers,correct,skills,family,difficulty"><?php echo $posted && isset( $_POST['text'] ) ? esc_textarea( wp_unslash( $_POST['text'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the page, escaped here. ?></textarea>
					<p><label for="oml-import-file"><?php esc_html_e( 'Or upload a CSV or JSON file (up to 2 MB):', 'ohmylms' ); ?></label> <input id="oml-import-file" type="file" name="file" accept=".csv,.json,.txt"></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="oml-import-bank"><?php esc_html_e( 'Question bank', 'ohmylms' ); ?></label></th>
				<td>
					<select id="oml-import-bank" name="bank_id">
						<option value="0"><?php esc_html_e( 'No bank (my own questions)', 'ohmylms' ); ?></option>
						<?php foreach ( $banks as $hub_bank ) : ?>
							<option value="<?php echo esc_attr( $hub_bank['id'] ); ?>"><?php echo esc_html( $hub_bank['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'ohmylms' ); ?></th>
				<td>
					<p><label><input type="checkbox" name="approve" value="1"> <?php esc_html_e( 'Approve imported questions so they can be used in practice and exams', 'ohmylms' ); ?></label></p>
					<p><label><input type="checkbox" name="update_existing" value="1" checked> <?php esc_html_e( 'Update questions that were imported before when their rows changed', 'ohmylms' ); ?></label></p>
					<p><label><input type="checkbox" name="skip_invalid" value="1"> <?php esc_html_e( 'Import the valid rows even if other rows have errors', 'ohmylms' ); ?></label></p>
				</td>
			</tr>
		</table>
		<p>
			<button type="submit" name="ohmylms_import_action" value="check" class="button button-secondary"><?php esc_html_e( 'Check rows', 'ohmylms' ); ?></button>
			<button type="submit" name="ohmylms_import_action" value="import" class="button button-primary"><?php esc_html_e( 'Import', 'ohmylms' ); ?></button>
		</p>
	</form>

	<details style="max-width:1100px;margin-top:24px">
		<summary><strong><?php esc_html_e( 'Row format', 'ohmylms' ); ?></strong></summary>
		<p><?php esc_html_e( 'CSV with a header row, or JSON as a list of objects. One row is one question.', 'ohmylms' ); ?></p>
		<ul style="list-style:disc;margin-left:20px">
			<li><code>exam</code>, <code>paper</code>, <code>year</code>, <code>section</code>, <code>number</code>, <code>language</code> <?php esc_html_e( 'identify the question. Number and an exam or paper name are required; language defaults to en. Uploading the same identity again updates that question.', 'ohmylms' ); ?></li>
			<li><code>type</code>: single-choice, multiple-choice, true-false, numerical, fill-in-the-blank <?php esc_html_e( 'or any other registered type (then add a settings JSON object and, if needed, options_raw).', 'ohmylms' ); ?></li>
			<li><code>question</code> <?php esc_html_e( 'is the text learners see. For fill-in-the-blank put each answer in braces: The capital is {Paris}.', 'ohmylms' ); ?></li>
			<li><code>answers</code> <?php esc_html_e( 'separated by |, and', 'ohmylms' ); ?> <code>correct</code> <?php esc_html_e( 'as a letter (B), a number (2), or the answer text; several with | for multiple choice. For numerical, correct is the number, with optional tolerance, tolerance_type (absolute or relative) and unit.', 'ohmylms' ); ?></li>
			<li><code>skills</code> <?php esc_html_e( 'separated by ; using skill codes, UUIDs or #termID. The first is the primary skill. Every question needs at least one.', 'ohmylms' ); ?></li>
			<li><code>family</code>, <code>difficulty</code> (easy, standard, challenge), <code>marks</code>, <code>explanation</code>, <code>image</code> <?php esc_html_e( '(a media library image ID or URL) are optional.', 'ohmylms' ); ?></li>
		</ul>
<pre>exam,paper,year,section,number,type,question,answers,correct,skills,family,difficulty
Digital SAT,2023-03 US,2023,Math M1,1,single-choice,"What is 2+2?",3|4|5|6,B,ALG.1,add-1,easy</pre>
	</details>
</div>
