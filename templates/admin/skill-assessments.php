<?php
/**
 * Skill assessments: build a quiz, unit test or course challenge from a list of skills.
 *
 * @package OhMyLMS
 *
 * @global array<int,string> $courses     Courses the user can edit.
 * @global array<int,string> $units       Units of the chosen course.
 * @global int               $course_id   Chosen course.
 * @global int               $chapter     Chosen unit.
 * @global array[]           $unit_skills Skills of the unit: id, code, name, available.
 * @global string            $search      Skill search words.
 * @global array[]           $found       Skills found by the search: id, code, name, available.
 * @global array             $form        Submitted values.
 * @global array|null        $created     The assessment just created.
 * @global string            $message     Error message.
 * @global array|null        $course_report Result of creating a unit test for every unit.
 * @global array             $styles      Style key to label and description.
 */

defined( 'ABSPATH' ) || exit;

$page_url            = admin_url( 'admin.php?page=' . \OhMyLMS\Assessment\SkillAssessmentPage::SLUG );
$assessment_form_url = add_query_arg(
	array(
		'course'  => $course_id,
		'chapter' => $chapter,
		'q'       => $search,
	),
	$page_url
);
// A skill is ticked when the unit teaches it, or when the form was sent with it ticked.
$ticked = $form['skills'];
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Skill assessments', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'Build a quiz, unit test or course challenge that draws a few approved questions for each skill you choose. Each attempt gets its own questions, and every question keeps its own skill, so results update the learner’s skill levels.', 'ohmylms' ); ?></p>

	<?php if ( $created ) : ?>
		<?php $assessment_edit_url = get_edit_post_link( $created['id'] ); ?>
		<div class="notice notice-success"><p>
			<?php
			/* translators: 1: assessment title, 2: number of questions, 3: number of skills */
			echo esc_html( sprintf( __( '“%1$s” was created with %2$d questions for %3$d skills.', 'ohmylms' ), $created['title'], $created['questions'], $created['skills'] ) );
			?>
			<?php echo 'publish' === $created['status'] ? esc_html__( 'It is published.', 'ohmylms' ) : esc_html__( 'It is a draft: publish it from the quiz editor when you are ready.', 'ohmylms' ); ?>
			<a href="<?php echo esc_url( $assessment_edit_url ? $assessment_edit_url : admin_url( 'admin.php?page=ohmylms' ) ); ?>"><?php esc_html_e( 'Open it', 'ohmylms' ); ?></a>
		</p></div>
	<?php endif; ?>
	<?php if ( '' !== $message ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $course_report ) ) : ?>
		<div class="notice notice-success"><p>
			<?php
			/* translators: %d: number of assessments */
			echo esc_html( sprintf( _n( '%d assessment was created.', '%d assessments were created.', count( $course_report['created'] ), 'ohmylms' ), count( $course_report['created'] ) ) );
			?>
		</p>
		<?php if ( $course_report['created'] ) : ?>
			<ul style="list-style:disc;margin-left:20px">
				<?php foreach ( $course_report['created'] as $item ) : ?>
					<li><?php echo esc_html( $item['title'] ); ?> — <?php /* translators: 1: questions, 2: skills */ echo esc_html( sprintf( __( '%1$d questions for %2$d skills', 'ohmylms' ), $item['questions'], $item['skills'] ) ); ?>, <?php echo 'publish' === $item['status'] ? esc_html__( 'published', 'ohmylms' ) : esc_html__( 'draft', 'ohmylms' ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $course_report['skipped'] ) : ?>
			<p><strong><?php esc_html_e( 'Skipped:', 'ohmylms' ); ?></strong></p>
			<ul style="list-style:disc;margin-left:20px">
				<?php foreach ( $course_report['skipped'] as $item ) : ?>
					<li><?php echo esc_html( $item['unit'] ); ?> — <?php echo esc_html( $item['reason'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $course_report['left_out'] ) : ?>
			<p><?php echo esc_html( sprintf( /* translators: %s: skill names */ __( 'Left out for lack of approved questions: %s.', 'ohmylms' ), implode( ', ', $course_report['left_out'] ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( $course_report['managed'] ) : ?>
			<p><?php esc_html_e( 'This course uses a learning program. Learners see the new assessments only after you add them to the program in the course’s learning program editor.', 'ohmylms' ); ?></p>
		<?php endif; ?>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( '1. Choose a unit (optional)', 'ohmylms' ); ?></h2>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( \OhMyLMS\Assessment\SkillAssessmentPage::SLUG ); ?>">
		<p>
			<label for="oml-sa-course"><?php esc_html_e( 'Course', 'ohmylms' ); ?></label>
			<select id="oml-sa-course" name="course">
				<option value="0"><?php esc_html_e( 'No course', 'ohmylms' ); ?></option>
				<?php foreach ( $courses as $assessment_course_id => $assessment_course_title ) : ?>
					<option value="<?php echo esc_attr( $assessment_course_id ); ?>" <?php selected( $course_id, $assessment_course_id ); ?>><?php echo esc_html( $assessment_course_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="oml-sa-chapter"><?php esc_html_e( 'Unit', 'ohmylms' ); ?></label>
			<select id="oml-sa-chapter" name="chapter">
				<option value="0"><?php esc_html_e( 'No unit', 'ohmylms' ); ?></option>
				<?php foreach ( $units as $assessment_unit_id => $assessment_unit_title ) : ?>
					<option value="<?php echo esc_attr( $assessment_unit_id ); ?>" <?php selected( $chapter, $assessment_unit_id ); ?>><?php echo esc_html( $assessment_unit_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Load', 'ohmylms' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Pick a course first, load it, then pick one of its units and load again to see the skills it teaches.', 'ohmylms' ); ?></p>
		<h2><?php esc_html_e( '2. Find more skills (optional)', 'ohmylms' ); ?></h2>
		<p>
			<label for="oml-sa-search"><?php esc_html_e( 'Search skills by name or code', 'ohmylms' ); ?></label>
			<input type="search" id="oml-sa-search" name="q" value="<?php echo esc_attr( $search ); ?>" class="regular-text">
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'ohmylms' ); ?></button>
		</p>
	</form>

	<form method="post" action="<?php echo esc_url( $assessment_form_url ); ?>">
		<?php wp_nonce_field( 'ohmylms_skill_assessment' ); ?>
		<input type="hidden" name="ohmylms_skill_assessment" value="1">
		<input type="hidden" name="course" value="<?php echo esc_attr( $course_id ); ?>">
		<input type="hidden" name="chapter" value="<?php echo esc_attr( $chapter ); ?>">

		<h2><?php esc_html_e( '3. Choose the skills', 'ohmylms' ); ?></h2>
		<?php
		$groups = array(
			__( 'Skills this unit teaches', 'ohmylms' ) => array( $unit_skills, true ),
			__( 'Search results', 'ohmylms' )           => array( $found, false ),
		);
		$any    = false;
		foreach ( $groups as $heading => list( $skills, $default_on ) ) :
			if ( ! $skills ) {
				continue;
			}
			$any = true;
			?>
			<h3><?php echo esc_html( $heading ); ?></h3>
			<table class="widefat striped" style="max-width:760px">
				<thead><tr><th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Use this skill', 'ohmylms' ); ?></span></th><th><?php esc_html_e( 'Skill', 'ohmylms' ); ?></th><th><?php esc_html_e( 'Different questions available', 'ohmylms' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $skills as $skill ) : ?>
					<?php $on = $created || $message ? in_array( $skill['id'], $ticked, true ) : $default_on; ?>
					<tr>
						<th class="check-column"><input type="checkbox" name="skills[]" id="oml-sa-skill-<?php echo esc_attr( $skill['id'] ); ?>" value="<?php echo esc_attr( $skill['id'] ); ?>" <?php checked( $on ); ?>></th>
						<td><label for="oml-sa-skill-<?php echo esc_attr( $skill['id'] ); ?>"><?php echo esc_html( $skill['name'] ); ?><?php echo '' !== $skill['code'] ? ' <code>' . esc_html( $skill['code'] ) . '</code>' : ''; ?></label></td>
						<td>
							<?php echo esc_html( (string) $skill['available'] ); ?>
							<?php if ( ! $skill['available'] ) : ?>
								<strong style="color:#b32d2e"><?php esc_html_e( 'None yet: approve questions for this skill first.', 'ohmylms' ); ?></strong>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
		<?php if ( ! $any ) : ?>
			<p class="description"><?php esc_html_e( 'No skills yet. Load a unit that has lessons with skills, or search for skills above.', 'ohmylms' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( '4. Set it up', 'ohmylms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="oml-sa-title"><?php esc_html_e( 'Title', 'ohmylms' ); ?></label></th>
				<td><input type="text" id="oml-sa-title" name="title" class="regular-text" value="<?php echo esc_attr( $form['title'] ); ?>"> <span class="description"><?php esc_html_e( 'Leave empty to use the style and the first skill.', 'ohmylms' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Style', 'ohmylms' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Style', 'ohmylms' ); ?></legend>
						<?php foreach ( $styles as $key => $style ) : ?>
							<label><input type="radio" name="style" value="<?php echo esc_attr( $key ); ?>" <?php checked( $form['style'], $key ); ?>> <strong><?php echo esc_html( $style[0] ); ?></strong> — <?php echo esc_html( $style[1] ); ?></label><br>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="oml-sa-per"><?php esc_html_e( 'Questions per skill', 'ohmylms' ); ?></label></th>
				<td><input type="number" id="oml-sa-per" name="per_skill" min="1" max="10" value="<?php echo esc_attr( max( 1, (int) $form['per_skill'] ) ); ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Each skill needs at least this many different questions (not variants of one another) in the bank.', 'ohmylms' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><label for="oml-sa-time"><?php esc_html_e( 'Time limit (minutes)', 'ohmylms' ); ?></label></th>
				<td><input type="number" id="oml-sa-time" name="time_limit" min="0" max="600" value="<?php echo esc_attr( (int) $form['time_limit'] ); ?>" class="small-text"> <span class="description"><?php esc_html_e( '0 means no limit; learners see a guide of one to two minutes per question.', 'ohmylms' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'When it is made', 'ohmylms' ); ?></th>
				<td>
					<label><input type="checkbox" name="publish" value="1" <?php checked( $form['publish'] ); ?>> <?php esc_html_e( 'Publish it now (otherwise it stays a draft)', 'ohmylms' ); ?></label><br>
					<label><input type="checkbox" name="place" value="1" <?php checked( $form['place'] ); ?> <?php disabled( ! $chapter ); ?>> <?php esc_html_e( 'Add it at the end of the chosen unit', 'ohmylms' ); ?></label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Create assessment', 'ohmylms' ) ); ?>
	</form>

	<?php if ( $course_id ) : ?>
		<hr>
		<h2><?php esc_html_e( 'Or: a unit test for every unit of this course', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'Creates one unit test for each unit that does not have one yet, from the skills its lessons teach, and adds it at the end of the unit. Skills without enough approved questions are left out and listed. Learners then see a Level up button on each unit.', 'ohmylms' ); ?></p>
		<form method="post" action="<?php echo esc_url( add_query_arg( array( 'course' => $course_id ), $page_url ) ); ?>">
			<?php wp_nonce_field( 'ohmylms_course_assessments' ); ?>
			<input type="hidden" name="ohmylms_course_assessments" value="1">
			<input type="hidden" name="course" value="<?php echo esc_attr( $course_id ); ?>">
			<p>
				<label for="oml-sa-course-per"><?php esc_html_e( 'Questions per skill', 'ohmylms' ); ?></label>
				<input type="number" id="oml-sa-course-per" name="per_skill" min="1" max="10" value="1" class="small-text">
			</p>
			<p><label><input type="checkbox" name="challenge" value="1"> <?php esc_html_e( 'Also create a course challenge from all the skills', 'ohmylms' ); ?></label></p>
			<p><label><input type="checkbox" name="publish" value="1"> <?php esc_html_e( 'Publish them now (otherwise they stay drafts)', 'ohmylms' ); ?></label></p>
			<?php submit_button( __( 'Create for every unit', 'ohmylms' ), 'secondary' ); ?>
		</form>
	<?php endif; ?>
</div>
