<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2020, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace ExpressionEngine\Controller\Publish;

use ExpressionEngine\Controller\Publish\AbstractPublish as AbstractPublishController;
use ExpressionEngine\Library\CP\Table;
use ExpressionEngine\Model\Channel\ChannelEntry as ChannelEntry;
use ExpressionEngine\Service\Validation\Result as ValidationResult;
use Mexitek\PHPColors\Color;

/**
 * Publish/Edit Controller
 */
class Edit extends AbstractPublishController {

	protected $permissions;

	public function __construct()
	{
		parent::__construct();

		$this->permissions = [
			'all'    => [],
			'others' => [],
			'self'   => [],
		];

		foreach ($this->assigned_channel_ids as $channel_id)
		{
			$this->permissions['others'][] = 'can_edit_other_entries_channel_id_' . $channel_id;
			$this->permissions['self'][]   = 'can_edit_self_entries_channel_id_' . $channel_id;
		}

		$this->permissions['all'] = array_merge($this->permissions['others'], $this->permissions['self']);

		if ( ! ee('Permission')->hasAny($this->permissions['all']))
		{
			show_error(lang('unauthorized_access'), 403);
		}
	}

	/**
	 * Displays all available entries
	 *
	 * @return void
	 */
	public function index()
	{
		if (ee()->input->post('bulk_action') == 'remove')
		{
			$this->remove(ee()->input->post('selection'));
		}

		$vars = array();
		$vars['channels_exist'] = true;

		$base_url = ee('CP/URL')->make('publish/edit');

		$entry_listing = ee('CP/EntryListing',
			ee()->input->get_post('filter_by_keyword'),
			ee()->input->get_post('search_in') ?: 'titles',
			ee('Permission')->hasAny($this->permissions['others'])
		);

		$entries = $entry_listing->getEntries();
		$filters = $entry_listing->getFilters();
		$channel_id = $entry_listing->channel_filter->value();

		if ( ! ee('Permission')->hasAny($this->permissions['others']))
		{
			$entries->filter('author_id', ee()->session->userdata('member_id'));
		}

		$count = $entry_listing->getEntryCount();

		// if no entries check to see if we have any channels
		if(empty($count))
		{
			// cast to bool
			$vars['channels_exist']  = (bool)ee('Model')->get('Channel')->filter('site_id', ee()->config->item('site_id'))->count();
		}

		$vars['filters'] = $filters->render($base_url);
		$vars['search_value'] = htmlentities(ee()->input->get_post('filter_by_keyword'), ENT_QUOTES, 'UTF-8');

		$filter_values = $filters->values();
		$base_url->addQueryStringVariables($filter_values);

		$table = ee('CP/Table', array(
			'sort_dir' => 'desc',
			'sort_col' => 'column_entry_date',
		));

		$columns = array(
			'column_entry_id',
			'column_title' => array(
				'encode' => FALSE
			)
		);

		$show_comments_column = (
			ee()->config->item('enable_comments') == 'y' OR
			ee('Model')->get('Comment')
				->filter('site_id', ee()->config->item('site_id'))
				->count() > 0);

		if ($show_comments_column)
		{
			$columns = array_merge($columns, array(
				'column_comment_total' => array(
					'encode' => FALSE
				)
			));
		}

		$columns = array_merge($columns, array(
			'column_entry_date',
			'column_status' => [
				'encode' => FALSE
			],
			array(
				'type'	=> Table::COL_CHECKBOX
			)
		));

		$table->setColumns($columns);
		if($vars['channels_exist'])
		{
			$table->setNoResultsText(lang('no_entries_exist'));
		}
		else
		{
			$table->setNoResultsText(
			sprintf(lang('no_found'), lang('channels'))
			.' <a href="'.ee('CP/URL', 'channels/create').'">'.lang('add_new').'</a>');
		}


		$show_new_button = $vars['channels_exist'];
		if ($channel_id)
		{
			$channel = $entry_listing->getChannelModelFromFilter();

			// Have we reached the max entries limit for this channel?
			if ($channel->maxEntriesLimitReached())
			{
				// Don't show New button
				$show_new_button = FALSE;

				$desc_key = ($channel->max_entries == 1)
					? 'entry_limit_reached_one_desc' : 'entry_limit_reached_desc';
				ee('CP/Alert')->makeInline()
					->asWarning()
					->withTitle(lang('entry_limit_reached'))
					->addToBody(sprintf(lang($desc_key), $channel->max_entries))
					->now();
			}
		}

		$page = ((int) ee()->input->get('page')) ?: 1;
		$offset = ($page - 1) * $filter_values['perpage']; // Offset is 0 indexed

		$entries->order(str_replace('column_', '', $table->sort_col), $table->sort_dir)
			->limit($filter_values['perpage'])
			->offset($offset);

		$data = array();

		$entry_id = ee()->session->flashdata('entry_id');

		$statuses = ee('Model')->get('Status')->all()->indexBy('status');

		foreach ($entries->all() as $entry)
		{
			if (ee('Permission')->can('edit_other_entries_channel_id_' . $entry->channel_id)
				|| (ee('Permission')->can('edit_self_entries_channel_id_' . $entry->channel_id) &&
					$entry->author_id == ee()->session->userdata('member_id')
					)
				)
			{
				$can_edit = TRUE;
			}
			else
			{
				$can_edit = FALSE;
			}

			// wW had a delete cascade issue that could leave entries orphaned and
			// resulted in errors, so we'll sneakily use this controller to clean up
			// for now.
			if (is_null($entry->Channel))
			{
				$entry->delete();
				continue;
			}

			$autosaves = $entry->Autosaves->count();

			// Escape markup in title
			$escaped_title = htmlentities($entry->title, ENT_QUOTES, 'UTF-8');

			if ($can_edit)
			{
				$edit_link = ee('CP/URL')->make('publish/edit/entry/' . $entry->entry_id);
				$title = '<a href="' . $edit_link . '">' . $escaped_title . '</a>';
			}
			else
			{
				$title = $escaped_title;
			}

			if ($autosaves)
			{
				$title .= ' <span class="auto-save" title="' . lang('auto_saved') . '">&#10033;</span>';
			}

			$title .= '<br><span class="meta-info">&mdash; ' . lang('by') . ': ' . htmlentities($entry->getAuthorName(), ENT_QUOTES, 'UTF-8') . ', ' . lang('in') . ': ' . htmlentities($entry->Channel->channel_title, ENT_QUOTES, 'UTF-8') . '</span>';

			if ($entry->comment_total > 0)
			{
				if (ee('Permission')->can('moderate_comments'))
				{
					$comments = '<a href="' . ee('CP/URL')->make('publish/comments/entry/' . $entry->entry_id) . '">' . $entry->comment_total . '</a>';
				}
				else
				{
					$comments = $entry->comment_total;
				}
			}
			else
			{
				$comments = '0';
			}

			if (ee('Permission')->can('delete_all_entries_channel_id_' . $entry->channel_id)
				|| (ee('Permission')->can('delete_self_entries_channel_id_' . $entry->channel_id) &&
					$entry->author_id == ee()->session->userdata('member_id')
					)
				)
			{
				$can_delete = TRUE;
			}
			else
			{
				$can_delete = FALSE;
			}

			$disabled_checkbox = ! $can_edit && ! $can_delete;

			// Display status highlight if one exists
			$status = isset($statuses[$entry->status]) ? $statuses[$entry->status] : NULL;

			if ($status)
			{
				$status = $status->renderTag();
			}
			else
			{
				$status = (in_array($entry->status, array('open', 'closed'))) ? lang($entry->status) : $entry->status;
			}

			$column = array(
				$entry->entry_id,
				$title,
				ee()->localize->human_time($entry->entry_date),
				$status,
				array(
					'name' => 'selection[]',
					'value' => $entry->entry_id,
					'disabled' => $disabled_checkbox,
					'data' => array(
						'title' => $escaped_title,
						'channel-id' => $entry->Channel->getId(),
						'confirm' => lang('entry') . ': <b>' . $escaped_title . '</b>'
					)
				)
			);

			if ($show_comments_column)
			{
				array_splice($column, 2, 0, array($comments));
			}

			$attrs = array();

			if ($entry_id && $entry->entry_id == $entry_id)
			{
				$attrs = array('class' => 'selected');
			}

			if ($autosaves)
			{
				$attrs = array('class' => 'auto-saved');
			}

			$data[] = array(
				'attrs'		=> $attrs,
				'columns'	=> $column
			);

		}
		$table->setData($data);

		$vars['table'] = $table->viewData($base_url);
		$vars['form_url'] = $vars['table']['base_url'];

		$menu = ee()->menu->generate_menu();
		$choices = [];
		foreach ($menu['channels']['create'] as $text => $link) {
			$choices[$link->compile()] = $text;
		}

		ee()->view->header = array(
			'title' => lang('entry_manager'),
			'action_button' => (count($choices) || ee('Permission')->can('create_entries_channel_id_' . $channel_id)) && $show_new_button ? [
				'text' => $channel_id ? sprintf(lang('btn_create_new_entry_in_channel'), $channel->channel_title) : lang('new'),
				'href' => ee('CP/URL', 'publish/create/' . $channel_id)->compile(),
				'filter_placeholder' => lang('filter_channels'),
				'choices' => $channel_id ? NULL : $choices
			] : NULL
		);

		$vars['pagination'] = ee('CP/Pagination', $count)
			->perPage($filter_values['perpage'])
			->currentPage($page)
			->render($base_url);

		ee()->javascript->set_global([
			'lang.remove_confirm' => lang('entry') . ': <b>### ' . lang('entries') . '</b>',

			'publishEdit.sequenceEditFormUrl' => ee('CP/URL')->make('publish/edit/entry/###')->compile(),
			'publishEdit.bulkEditFormUrl' => ee('CP/URL')->make('publish/bulk-edit')->compile(),
			'publishEdit.addCategoriesFormUrl' => ee('CP/URL')->make('publish/bulk-edit/categories/add')->compile(),
			'publishEdit.removeCategoriesFormUrl' => ee('CP/URL')->make('publish/bulk-edit/categories/remove')->compile(),
			'bulkEdit.lang' => [
				'selectedEntries'       => lang('selected_entries'),
				'filterSelectedEntries' => lang('filter_selected_entries'),
				'noEntriesFound'        => sprintf(lang('no_found'), lang('entries')),
				'showing'               => lang('showing'),
				'of'                    => lang('of'),
				'clearAll'              => lang('clear_all'),
				'removeFromSelection'   => lang('remove_from_selection'),
			]
		]);

		ee()->cp->add_js_script(array(
			'file' => array(
				'cp/confirm_remove',
				'cp/publish/entry-list',
				'components/bulk_edit_entries',
				'cp/publish/bulk-edit'
			),
		));

		ee()->view->cp_page_title = lang('edit_channel_entries');
		if ( ! empty($filter_values['filter_by_keyword']))
		{
			$vars['cp_heading'] = sprintf(lang('search_results_heading'), $count, $filter_values['filter_by_keyword']);
		}
		else
		{
			$vars['cp_heading'] = sprintf(
				lang('all_channel_entries'),
				(isset($channel->channel_title)) ? $channel->channel_title : ''
			);
		}

		if ($channel_id)
		{
			$vars['can_edit']   = ee('Permission')->hasAny(
				'can_edit_self_entries_channel_id_' . $channel_id,
				'can_edit_other_entries_channel_id_' . $channel_id
			);

			$vars['can_delete'] = ee('Permission')->hasAny(
				'can_delete_all_entries_channel_id_' . $channel_id,
				'can_delete_self_entries_channel_id_' . $channel_id
			);
		}
		else
		{
			$edit_perms = [];
			$del_perms  = [];

			foreach ($entries->all()->pluck('channel_id') as $channel_id)
			{
				$edit_perms[] = 'can_edit_self_entries_channel_id_' . $channel_id;
				$edit_perms[] = 'can_edit_other_entries_channel_id_' . $channel_id;

				$del_perms[] = 'can_delete_all_entries_channel_id_' . $channel_id;
				$del_perms[] = 'can_delete_self_entries_channel_id_' . $channel_id;
			}

			$vars['can_edit']   = (empty($edit_perms)) ? FALSE : ee('Permission')->hasAny($edit_perms);
			$vars['can_delete'] = (empty($del_perms)) ? FALSE : ee('Permission')->hasAny($del_perms);
		}

		if (AJAX_REQUEST)
		{
			return array(
				'html' => ee('View')->make('publish/partials/edit_list_table')->render($vars),
				'url' => $vars['form_url']->compile()
			);
		}

		ee()->cp->render('publish/edit/index', $vars);
	}

	public function entry($id = NULL, $autosave_id = NULL)
	{
		if ( ! $id)
		{
			show_404();
		}

		$base_url = ee('CP/URL')->getCurrentUrl();

		// Sequence editing?
		$sequence_editing = FALSE;
		if ($entry_ids = ee('Request')->get('entry_ids'))
		{
			$sequence_editing = TRUE;

			$index = array_search($id, $entry_ids) + 1;
			$next_entry_id = isset($entry_ids[$index]) ? $entry_ids[$index] : NULL;
			$base_url->setQueryStringVariable('next_entry_id', $next_entry_id);
		}

		// If an entry or channel on a different site is requested, try
		// to switch sites and reload the publish form
		$site_id = (int) ee()->input->get_post('site_id');
		if ($site_id != 0 && $site_id != ee()->config->item('site_id') && empty($_POST))
		{
			ee()->cp->switch_site($site_id, $base_url);
		}

		$entry = ee('Model')->get('ChannelEntry', $id)
			->with('Channel')
			->filter('site_id', ee()->config->item('site_id'))
			->first();

		if ( ! $entry)
		{
			show_error(lang('no_entries_matching_that_criteria'));
		}

		if ( ! ee('Permission')->can('edit_other_entries_channel_id_' . $entry->channel_id)
			&& $entry->author_id != ee()->session->userdata('member_id'))
		{
			show_error(lang('unauthorized_access'), 403);
		}

		if ( ! in_array($entry->channel_id, $this->assigned_channel_ids))
		{
			show_error(lang('unauthorized_access'), 403);
		}

		// -------------------------------------------
		// 'publish_form_entry_data' hook.
		//  - Modify entry's data
		//  - Added: 1.4.1
			if (ee()->extensions->active_hook('publish_form_entry_data') === TRUE)
			{
				$result = ee()->extensions->call('publish_form_entry_data', $entry->getValues());
				$entry->set($result);
			}
		// -------------------------------------------

		$entry_title = htmlentities($entry->title, ENT_QUOTES, 'UTF-8');
		ee()->view->cp_page_title = sprintf(lang('edit_entry_with_title'), $entry_title);

		$form_attributes = array(
			'class' => 'ajax-validate',
		);

		$vars = array(
			'header' => [
				'title' => lang('edit_entry'),
			],
			'form_url' => $base_url,
			'form_attributes' => $form_attributes,
			'form_title' => lang('edit_entry'),
			'errors' => new \ExpressionEngine\Service\Validation\Result,
			'autosaves' => $this->getAutosavesTable($entry, $autosave_id),
			'extra_publish_controls' => $entry->Channel->extra_publish_controls,
			'buttons' => $this->getPublishFormButtons($entry),
			'in_modal_context' => $sequence_editing
		);

		if (ee()->input->get('hide_closer')==='y' && ee()->input->get('return')!='')
		{
			$vars['form_hidden'] = ['return'	=> urldecode(ee()->input->get('return'))];
			$vars['hide_sidebar'] = true;
		}

		if ($sequence_editing)
		{
			$vars['modal_title'] = sprintf('(%d of %d) %s', $index, count($entry_ids), $entry_title);
			$vars['buttons'] = [[
				'name' => 'submit',
				'type' => 'submit',
				'value' => 'save_and_next',
				'text' => $index == count($entry_ids) ? 'save_and_close' : 'save_and_next',
				'working' => 'btn_saving'
			]];
		}

		if ($entry->isLivePreviewable()) {

			$action_id = ee()->db->select('action_id')
				->where('class', 'Channel')
				->where('method', 'live_preview')
				->get('actions');
			$preview_url = ee()->functions->fetch_site_index().QUERY_MARKER.'ACT='.$action_id->row('action_id').AMP.'channel_id='.$entry->channel_id.AMP.'entry_id='.$entry->entry_id;
			if (ee()->input->get('return')!='')
			{
				$preview_url .= AMP . 'return='. urlencode(ee()->input->get('return'));
			}
			$modal_vars = [
				'preview_url' => $preview_url,
				'hide_closer'	=> ee()->input->get('hide_closer')==='y' ? true : false
			];
			$modal = ee('View')->make('publish/live-preview-modal')->render($modal_vars);
			ee('CP/Modal')->addModal('live-preview', $modal);

		} elseif (ee('Permission')->hasAll('can_admin_channels', 'can_edit_channels')) {

			$lp_setup_alert = ee('CP/Alert')->makeBanner('live-preview-setup')
				->asIssue()
				->canClose()
				->withTitle(lang('preview_url_not_set'))
				->addToBody(sprintf(lang('preview_url_not_set_desc'), ee('CP/URL')->make('channels/edit/'.$entry->channel_id)->compile().'#tab=t-4&id=fieldset-preview_url'));
			ee()->javascript->set_global('alert.lp_setup', $lp_setup_alert->render());

		}

		$version_id = ee()->input->get('version');

		if ($entry->Channel->enable_versioning)
		{
			$vars['revisions'] = $this->getRevisionsTable($entry, $version_id);
		}

		if ($version_id)
		{
			$version = $entry->Versions->filter('version_id', $version_id)->first();
			$version_data = $version->version_data;
			$entry->set($version_data);
		}

		if ($autosave_id)
		{
			$autosaved = ee('Model')->get('ChannelEntryAutosave', $autosave_id)
				->filter('site_id', ee()->config->item('site_id'))
				->first();

			if ($autosaved)
			{
				$entry->set($autosaved->entry_data);
			}
		}

		$channel_layout = ee('Model')->get('ChannelLayout')
			->filter('site_id', ee()->config->item('site_id'))
			->filter('channel_id', $entry->channel_id)
			->with('PrimaryRoles')
			->filter('PrimaryRoles.role_id', ee()->session->userdata('role_id'))
			->first();

		$vars['layout'] = $entry->getDisplay($channel_layout);

		$result = $this->validateEntry($entry, $vars['layout']);

		if ($result instanceOf ValidationResult)
		{
			$vars['errors'] = $result;

			if ($result->isValid())
			{
				return $this->saveEntryAndRedirect($entry);
			}
		}

		$vars['entry'] = $entry;

		$this->setGlobalJs($entry, TRUE);

		ee()->cp->add_js_script(array(
			'plugin' => array(
				'ee_url_title',
				'ee_filebrowser',
				'ee_fileuploader',
			),
			'file' => array('cp/publish/publish')
		));

		ee()->view->cp_breadcrumbs = array(
			ee('CP/URL')->make('publish/edit', array('filter_by_channel' => $entry->channel_id))->compile() => $entry->Channel->channel_title,
		);

		if (ee('Request')->get('modal_form') == 'y')
		{
			$vars['layout']->setIsInModalContext(TRUE);
			ee()->output->enable_profiler(FALSE);
			return ee()->view->render('publish/modal-entry', $vars);
		}

		ee()->cp->render('publish/entry', $vars);
	}

	private function remove($entry_ids)
	{
		$perms = [];

		foreach ($this->assigned_channel_ids as $channel_id)
		{
			$perms[] = 'can_delete_all_entries_channel_id_' . $channel_id;
			$perms[] = 'can_delete_self_entries_channel_id_' . $channel_id;
		}

		if ( ! ee('Permission')->hasAny($perms))
		{
			show_error(lang('unauthorized_access'), 403);
		}

		if ( ! is_array($entry_ids))
		{
			$entry_ids = array($entry_ids);
		}

		$entry_names = array_merge($this->removeAllEntries($entry_ids), $this->removeSelfEntries($entry_ids));

		ee('CP/Alert')->makeInline('entries-form')
			->asSuccess()
			->withTitle(lang('success'))
			->addToBody(lang('entries_deleted_desc'))
			->addToBody($entry_names)
			->defer();

		ee()->functions->redirect(ee('CP/URL')->make('publish/edit', ee()->cp->get_url_state()));
	}

	private function removeEntries($entry_ids, $self_only = true)
	{
		$entries = ee('Model')->get('ChannelEntry', $entry_ids)
			->filter('site_id', ee()->config->item('site_id'));

		if (!$this->is_admin) {
			if (empty($this->assigned_channel_ids)) {
				show_error(lang('no_channels'));
			}

			if ($self_only == true) {
				$entries->filter('author_id', ee()->session->userdata('member_id'));
			}

			$channel_ids = [];
			$permission = ($self_only == true) ? 'delete_self_entries' : 'delete_all_entries';

			foreach ($this->assigned_channel_ids as $channel_id)
			{
				if (ee('Permission')->can($permission . '_channel_id_' . $channel_id))
				{
					$channel_ids[] = $channel_id;
				}
			}

			// No permission to delete self entries
			if (empty($channel_ids))
			{
				return [];
			}

			$entries->filter('channel_id', 'IN', $this->assigned_channel_ids);
		}

		$all_entries = $entries->all();
		if (!empty($all_entries)) {
			$entry_names = $all_entries->pluck('title');
			$entry_ids = $all_entries->pluck('entry_id');

			// Remove pages URIs
			$site_id = ee()->config->item('site_id');
			$site_pages = ee()->config->item('site_pages');

			if ($site_pages !== FALSE && count($site_pages[$site_id]) > 0) {
				foreach ($all_entries as $entry){
					unset($site_pages[$site_id]['uris'][$entry->entry_id]);
					unset($site_pages[$site_id]['templates'][$entry->entry_id]);

					ee()->config->set_item('site_pages', $site_pages);

					$entry->Site->site_pages = $site_pages;
					$entry->Site->save();
				}
			}
		}

		$entries->delete();

		return $entry_names;
	}


	private function removeAllEntries($entry_ids)
	{
		return $this->removeEntries($entry_ids);
	}

	private function removeSelfEntries($entry_ids)
	{
		return $this->removeEntries($entry_ids, true);
	}
}

// EOF