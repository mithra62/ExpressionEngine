<?php use ExpressionEngine\Library\CP\Table; ?>

<?php /* Table */ if (!$grid_input): ?>

<?php if ($wrap): ?>
	<div class="table-responsive table-responsive--collapsible">
<?php endif ?>

<?php if (empty($columns) && empty($data)): ?>
	<table cellspacing="0" class="empty no-results">
		<tr>
			<td>
				<?=lang($no_results['text'])?>
				<?php if ( ! empty($no_results['action_text'])): ?>
					<a <?=$no_results['external'] ? 'rel="external"' : '' ?> href="<?=$no_results['action_link']?>"><?=lang($no_results['action_text'])?></a>>
				<?php endif ?>
			</td>
		</tr>
	</table>
<?php else: ?>
	<table cellspacing="0" <?php if ($class): ?>class="<?=$class?>"<?php endif ?> <?php foreach ($table_attrs as $key => $value):?> <?=$key?>='<?=$value?>'<?php endforeach; ?>>
		<thead>
			<tr>
				<?php
				// Don't do reordering logic if the table is empty
				$reorder = $reorder && ! empty($data);
				$colspan = ($reorder_header || $reorder) ? count($columns) + 1 : count($columns);

				if ($reorder_header): ?>
					<th class="reorder-col"><span class="ico reorder fas fa-bars"></span></th>
				<?php elseif ($reorder): ?>
					<th class="first reorder-col"></th>
				<?php endif ?>
				<?php foreach ($columns as $settings):
					$attrs = (isset($settings['attrs'])) ? $settings['attrs'] : array();
					$label = $settings['label']; ?>
					<?php if ($settings['type'] == Table::COL_CHECKBOX): ?>
						<th class="check-ctrl">
							<?php if ( ! empty($data) OR $checkbox_header): // Hide checkbox if no data ?>
								<?php if (isset($settings['content'])): ?>
									<?=$settings['content']?>
								<?php else: ?>
									<input type="checkbox" title="select all">
								<?php endif ?>
							<?php endif ?>
						</th>
					<?php else: ?>
						<?php
						$header_class = '';
						$header_sorts = ($sortable && $settings['sort'] && $base_url != NULL);

						if ($settings['type'] == Table::COL_ID)
						{
							$header_class .= ' id-col';
						}
						if ($header_sorts)
						{
							$header_class .= ' column-sort-header';
						}
						if ($sortable && $settings['sort'] && $sort_col == $label)
						{
							$header_class .= ' column-sort-header--active';
						}
						if (isset($settings['class']))
						{
							$header_class .= ' '.$settings['class'];
						}
						?>
						<th<?php if ( ! empty($header_class)): ?> class="<?=trim($header_class)?>"<?php endif ?><?php foreach ($attrs as $key => $value):?> <?=$key?>="<?=$value?>"<?php endforeach; ?>>
							<?php if ($header_sorts): ?>
								<?php
								$url = clone $base_url;
								$arrow_dir = ($sort_col == $label) ? $sort_dir : 'desc';
								$link_dir = ($arrow_dir == 'asc') ? 'desc' : 'asc';
								$url->setQueryStringVariable($sort_col_qs_var, $label);
								$url->setQueryStringVariable($sort_dir_qs_var, $link_dir);
								?>
								<a href="<?=$url?>" class="column-sort column-sort--<?=$arrow_dir?>">
							<?php endif ?>

							<?php if (isset($settings['required']) && $settings['required']): ?><span class="required"><?php endif; ?>
							<?=($lang_cols) ? lang($label) : $label ?>
							<?php if (isset($settings['required']) && $settings['required']): ?></span><?php endif; ?>
							<?php if (isset($settings['desc']) && ! empty($settings['desc'])): ?>
								<span class="grid-instruct"><?=lang($settings['desc'])?></span>
							<?php endif ?>

							<?php if ($header_sorts): ?>
								</a>
							<?php endif ?>
						</th>
					<?php endif ?>
				<?php endforeach ?>
			</tr>
		</thead>
		<tbody>
			<?php
			// Output this if Grid input so we can dynamically show it via JS
			if (empty($data)): ?>
				<tr class="no-results<?php if ( ! empty($action_buttons) || ! empty($action_content)): ?> last<?php endif?>">
					<td class="solo" colspan="<?=$colspan?>">
						<?=lang($no_results['text'])?>
						<?php if ( ! empty($no_results['action_text'])): ?>
							<a rel="add_row" <?=$no_results['external'] ? 'rel="external"' : '' ?> href="<?=$no_results['action_link']?>"><?=lang($no_results['action_text'])?></a>
						<?php endif ?>
					</td>
				</tr>
			<?php endif ?>
			<?php $i = 1;
			foreach ($data as $heading => $rows): ?>
				<?php if ( ! $subheadings)
				{
					$rows = array($rows);
				}
				if ($subheadings && ! empty($heading)): ?>
					<tr class="sub-heading"><td colspan="<?=$colspan?>"><?=lang($heading)?></td></tr>
				<?php endif ?>
				<?php
				foreach ($rows as $row):
					// The last row preceding an action row should have a class of 'last'
					if (( ! empty($action_buttons) || ! empty($action_content)) && $i == min($total_rows, $limit))
					{
						if (isset($row['attrs']['class']))
						{
							$row['attrs']['class'] .= ' last';
						}
						else
						{
							$row['attrs']['class'] = ' last';
						}
					}
					$i++;
					?>
					<tr<?php foreach ($row['attrs'] as $key => $value):?> <?=$key?>="<?=$value?>"<?php endforeach; ?>>
						<?php if ($reorder): ?>
							<td class="reorder-col"><span class="ico reorder fas fa-bars"></span></td>
						<?php endif ?>
						<?php foreach ($row['columns'] as $key => $column):
							$column_name = $columns[$key]['label'];
							$column_name = ($lang_cols) ? lang($column_name) : $column_name;
							?>

							<?php if ($column['encode'] == TRUE && $column['type'] != Table::COL_STATUS): ?>
								<?php if (isset($column['href'])): ?>
								<td><span class="collapsed-label"><?=$column_name?></span><a href="<?=$column['href']?>"><?=htmlentities($column['content'], ENT_QUOTES, 'UTF-8')?></a></td>
								<?php else: ?>
								<td><span class="collapsed-label"><?=$column_name?></span><?=htmlentities($column['content'], ENT_QUOTES, 'UTF-8')?></td>
								<?php endif; ?>
							<?php elseif ($column['type'] == Table::COL_TOOLBAR): ?>
								<td>
									<div class="toolbar-wrap">
										<?=ee()->load->view('_shared/toolbar', $column, TRUE)?>
									</div>
								</td>
							<?php elseif ($column['type'] == Table::COL_CHECKBOX): ?>
								<td>
									<input
										name="<?=form_prep($column['name'])?>"
										value="<?=form_prep($column['value'])?>"
										<?php if (isset($column['data'])):?>
											<?php foreach ($column['data'] as $key => $value): ?>
												data-<?=$key?>="<?=form_prep($value)?>"
											<?php endforeach; ?>
										<?php endif; ?>
										<?php if (isset($column['disabled']) && $column['disabled'] !== FALSE):?>
											disabled="disabled"
										<?php endif; ?>
										type="checkbox"
									>
								</td>
							<?php elseif ($column['type'] == Table::COL_STATUS): ?>
								<?php
									$class = isset($column['class']) ? $column['class'] : $column['content'];
									$style = 'style="';

									// override for open/closed
									if (isset($column['status']) && in_array($column['status'], array('open', 'closed')))
									{
										$class = $column['status'];
									}
									else
									{
										if (isset($column['background-color']) && $column['background-color'])
										{
											$style .= 'background-color: #'.$column['background-color'].';';
											$style .= 'border-color: #'.$column['background-color'].';';
										}

										if (isset($column['color']) && $column['color'])
										{
											$style .= 'color: #'.$column['color'].';';
										}
									}

									$style .= '"';
								?>
								<td><span class="collapsed-label"><?=$column_name?></span><span class="status-tag st-<?=strtolower($class)?>" <?=$style?>><?=$column['content']?></span></td>
							<?php elseif (isset($column['html'])): ?>
								<td<?php if (isset($column['error']) && ! empty($column['error'])): ?> class="invalid"<?php endif ?> <?php if (isset($column['attrs'])): foreach ($column['attrs'] as $key => $value):?> <?=$key?>="<?=$value?>"<?php endforeach; endif; ?>>
									<span class="collapsed-label"><?=$column_name?></span>
									<?=$column['html']?>
									<?php if (isset($column['error']) && ! empty($column['error'])): ?>
										<em class="ee-form-error-message"><?=$column['error']?></em>
									<?php endif ?>
								</td>
							<?php else: ?>
								<td><span class="collapsed-label"><?=$column_name?></span><?=$column['content']?></td>
							<?php endif ?>
						<?php endforeach ?>
					</tr>
				<?php endforeach ?>
			<?php endforeach ?>
			<?php if ( ! empty($action_buttons) || ! empty($action_content)): ?>
				<tr class="tbl-action">
					<td colspan="<?=$colspan?>" class="solo">
						<?php foreach ($action_buttons as $button): ?>
							<a class="<?=$button['class']?>" href="<?=$button['url']?>"><?=$button['text']?></a></td>
						<?php endforeach; ?>
						<?=$action_content?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
<?php endif ?>

<?php if ($wrap): ?>
	</div>
<?php endif ?>


<?php /* End table */

else: ?>

	<div class="grid-field" id="<?=$grid_field_name?>">

	<div class="table-responsive">
	<table class="grid-field__table"<?php foreach ($table_attrs as $key => $value):?> <?=$key?>='<?=$value?>'<?php endforeach; ?>>
	<?php if (empty($columns) && empty($data)): ?>
		<p class="no-results">
			<?=lang($no_results['text'])?>
			<?php if ( ! empty($no_results['action_text'])): ?>
				<a <?=$no_results['external'] ? 'rel="external"' : '' ?> href="<?=$no_results['action_link']?>"><?=lang($no_results['action_text'])?></a>>
			<?php endif ?>
		</p>
	<?php else: ?>
		<thead>
				<?php
				// Don't do reordering logic if the table is empty
				$reorder = $reorder && ! empty($data);
				$colspan = ($reorder_header || $reorder) ? count($columns) + 1 : count($columns);

				foreach ($columns as $settings):
					$attrs = (isset($settings['attrs'])) ? $settings['attrs'] : array();
					$label = $settings['label']; ?>
					<?php if ($settings['type'] == Table::COL_CHECKBOX): ?>
						<th class="check-ctrl">
							<?php if ( ! empty($data) OR $checkbox_header): // Hide checkbox if no data ?>
								<?php if (isset($settings['content'])): ?>
									<?=$settings['content']?>
								<?php else: ?>
									<input type="checkbox" title="select all">
								<?php endif ?>
							<?php endif ?>
						</th>
					<?php else: ?>
						<?php
						$header_class = '';
						$header_sorts = ($sortable && $settings['sort'] && $base_url != NULL);

						if ($settings['type'] == Table::COL_ID) {
							$header_class .= ' id-col';
						}
						if ($header_sorts) {
							$header_class .= ' column-sort-header';
						}
						if ($sortable && $settings['sort'] && $sort_col == $label) {
							$header_class .= ' column-sort-header--active';
						}
						if (isset($settings['class'])) {
							$header_class .= ' '.$settings['class'];
						}
						?>
						<th class="<?=$header_class?>" <?php foreach ($attrs as $key => $value):?> <?=$key?>="<?=$value?>"<?php endforeach; ?>>
							<?php if ($header_sorts): ?>
								<?php
								$url = clone $base_url;
								$arrow_dir = ($sort_col == $label) ? $sort_dir : 'desc';
								$link_dir = ($arrow_dir == 'asc') ? 'desc' : 'asc';
								$url->setQueryStringVariable($sort_col_qs_var, $label);
								$url->setQueryStringVariable($sort_dir_qs_var, $link_dir);
								?>
								<a href="<?=$url?>" class="column-sort column-sort--<?=$arrow_dir?>">
							<?php endif ?>

							<?php if (isset($settings['required']) && $settings['required']): ?><span class="required"><?php endif; ?>
							<?=($lang_cols) ? lang($label) : $label ?>
							<?php if (isset($settings['required']) && $settings['required']): ?></span><?php endif; ?>
							<?php if (isset($settings['desc']) && ! empty($settings['desc'])): ?>
								<span class="grid-instruct"><?=lang($settings['desc'])?></span>
							<?php endif ?>

							<?php if ($header_sorts): ?>
								</a>
							<?php endif ?>
						</th>
					<?php endif ?>
				<?php endforeach ?>

				<?php if (!empty($data)): ?>
					<th class="grid-field__column-remove"></th>
				<?php endif ?>
		</thead>
	<?php endif ?>

		<tbody>
			<tr class="no-results<?php if ( ! empty($action_buttons) || ! empty($action_content)): ?> last<?php endif?> <?php if (!empty($data)): ?>hidden<?php endif?>"><td colspan="<?=(count($columns)+intval($header_sorts))?>">
			<?php
			// Output this if Grid input so we can dynamically show it via JS
			?>
				<p>
					<?=lang($no_results['text'])?>
					<?php if ( ! empty($no_results['action_text'])): ?>
						<a rel="add_row" <?=$no_results['external'] ? 'rel="external"' : '' ?> href="<?=$no_results['action_link']?>"><?=lang($no_results['action_text'])?></a>
					<?php endif ?>
				</p>
			</td></tr>
			<?php $i = 1;
			foreach ($data as $heading => $rows): ?>
				<?php if ( ! $subheadings) {
					$rows = array($rows);
				}

				foreach ($rows as $row):
					$i++;

					$row_class = "";

					if (isset($row['attrs']['class'])) {
						$row_class = $row['attrs']['class'];
						unset($row['attrs']['class']);
					}
				?>
					<tr class="<?=$row_class?>" <?php foreach ($row['attrs'] as $key => $value):?> <?=$key?>="<?=$value?>"<?php endforeach; ?>>

						<?php foreach ($row['columns'] as $key => $column):
							$column_name = $columns[$key]['label'];
							$column_name = ($lang_cols) ? lang($column_name) : $column_name;
							$column_desc = '';

							if (isset($columns[$key]['desc']) && !empty($columns[$key]['desc'])) {
								$column_desc = lang($columns[$key]['desc']);
							}

							$column_label = "<div class=\"grid-field__column-label\">
								<div class=\"field-instruct\">
									<label>$column_name</label>
									<em>$column_desc</em>
								</div>
							</div>";

							?>

							<?php if ($column['encode'] == TRUE && $column['type'] != Table::COL_STATUS): ?>
								<?php if (isset($column['href'])): ?>
								<td><?=$column_label?><a href="<?=$column['href']?>"><?=htmlentities($column['content'], ENT_QUOTES, 'UTF-8')?></a></td>
								<?php else: ?>
								<td><?=$column_label?><?=htmlentities($column['content'], ENT_QUOTES, 'UTF-8')?></td>
								<?php endif; ?>
							<?php elseif ($column['type'] == Table::COL_TOOLBAR): ?>
								<td>
									<div class="toolbar-wrap">
										<?=ee()->load->view('_shared/toolbar', $column, TRUE)?>
									</div>
								</td>
							<?php elseif ($column['type'] == Table::COL_CHECKBOX): ?>
								<td>
									<input
										name="<?=form_prep($column['name'])?>"
										value="<?=form_prep($column['value'])?>"
										<?php if (isset($column['data'])):?>
											<?php foreach ($column['data'] as $key => $value): ?>
												data-<?=$key?>="<?=form_prep($value)?>"
											<?php endforeach; ?>
										<?php endif; ?>
										<?php if (isset($column['disabled']) && $column['disabled'] !== FALSE):?>
											disabled="disabled"
										<?php endif; ?>
										type="checkbox"
									>
								</td>
							<?php elseif ($column['type'] == Table::COL_STATUS): ?>
								<?php
									$class = isset($column['class']) ? $column['class'] : $column['content'];
									$style = 'style="';

									// override for open/closed
									if (isset($column['status']) && in_array($column['status'], array('open', 'closed')))
									{
										$class = $column['status'];
									}
									else
									{
										if (isset($column['background-color']) && $column['background-color'])
										{
											$style .= 'background-color: #'.$column['background-color'].';';
											$style .= 'border-color: #'.$column['background-color'].';';
										}

										if (isset($column['color']) && $column['color'])
										{
											$style .= 'color: #'.$column['color'].';';
										}
									}

									$style .= '"';
								?>
								<td><?=$column_label?><span class="status-tag st-<?=strtolower($class)?>" <?=$style?>><?=$column['content']?></span></td>
							<?php elseif (isset($column['html'])): ?>
								<td class="<?php if (isset($column['error']) && ! empty($column['error'])): ?>invalid<?php endif ?>" <?php if (isset($column['attrs'])): foreach ($column['attrs'] as $key => $value):?> <?=$key?>="<?=$value?>"<?php endforeach; endif; ?>>
									<?=$column_label?>
									<?=$column['html']?>
									<?php if (isset($column['error']) && ! empty($column['error'])): ?>
										<em class="ee-form-error-message"><?=$column['error']?></em>
									<?php endif ?>
								</td>
							<?php else: ?>
								<td><?=$column_label?><?=$column['content']?></td>
							<?php endif ?>
						<?php endforeach ?>

						<td class="grid-field__column--tools">
							<div class="grid-field__column-tools">
								<?php if ($reorder): ?>
								<button type="button" class="button button--small button--default">
									<a href class="grid-field__column-tool cursor-move js-grid-reorder-handle"><i class="fas fa-fw fa-arrows-alt"></i></a>
								</button>
								<?php endif ?>
								<button type="button" class="button button--small button--default">
									<a href rel="remove_row" class="grid-field__column-tool danger-link" title="<?=lang('remove_row')?>"><i class="fas fa-fw fa-trash-alt"></i></a>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach ?>
			<?php endforeach ?>
			</tbody>
	</table>
	</div>

	<div class="grid-field__footer">
		<div class="button-group">
			<?php if ( ! empty($action_buttons) || ! empty($action_content)): ?>
			<div class="tbl-action">
				<?php foreach ($action_buttons as $button): ?>
					<a class="<?=$button['class']?>" href="<?=$button['url']?>"><?=$button['text']?></a></td>
				<?php endforeach; ?>
				<?=$action_content?>
			</div>
			<?php endif; ?>
			<?php if ($show_add_button) : ?>
			<button type="button" rel="add_row" class="button button--default button--small js-grid-add-row"><?=lang('add_row')?></button>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endif ?>
