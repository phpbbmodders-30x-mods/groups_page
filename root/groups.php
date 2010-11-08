<?php
/**
*
* @package Groups page
* @version $Id: groups.php 06/11/2010 RMcGirr83
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';  // Remember and change this to refelct your site
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/groups');

// what groups is the user a member of?
// this allows hidden groups to display
$sql = 'SELECT group_id
	FROM ' . USER_GROUP_TABLE . '
	WHERE user_id = ' . (int) $user->data['user_id'] . '
	AND user_pending = 0';
$result = $db->sql_query($sql);
$in_group = $db->sql_fetchrowset($result);
$db->sql_freeresult($result);

// groups not displayed
// you can add to the array if wanted
// by adding the group name to ignore into the array
// default group names are GUESTS REGISTERED REGISTERED_COPPA GLOBAL_MODERATORS ADMINISTRATORS BOTS
$groups_not_display = array('GUESTS', 'BOTS', 'TESTING');

// don't want coppa group?
if (!$config['coppa_enable'])
{
	$no_coppa = array('REGISTERED_COPPA');
	$groups_not_display = array_merge($groups_not_display, $no_coppa);

	//free up a bit 'o memory
	unset($no_coppa);
}

// get the groups
$sql = 'SELECT *
	FROM ' . GROUPS_TABLE . '
	WHERE ' . $db->sql_in_set('group_name', $groups_not_display, true) . '
	ORDER BY group_name';
$result = $db->sql_query($sql);

$group_rows = array();
while ($row = $db->sql_fetchrow($result) )
{
	$group_rows[] = $row;
}
$db->sql_freeresult($result);

// Grab rank information for later
$ranks = $cache->obtain_ranks();

if ($total_groups = count($group_rows))
{
	// Obtain list of users of each group
	$sql = 'SELECT u.user_id, u.username, u.username_clean, u.user_colour, ug.group_id, ug.group_leader
			FROM ' . USER_GROUP_TABLE . ' ug, ' . USERS_TABLE . ' u
			WHERE ug.user_id = u.user_id
			AND ug.user_pending = 0
			AND u.user_id <> ' . ANONYMOUS . '
			ORDER BY  ug.group_leader DESC, u.username ASC';
	$result = $db->sql_query($sql);

	$group_users = $group_leaders = array();
	while ($row = $db->sql_fetchrow($result))
	{
		if ($row['group_leader'])
		{
			$group_leaders[$row['group_id']][] = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
		}
		else
		{
			$group_users[$row['group_id']][] = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
		}
	}
	$db->sql_freeresult($result);

	for($i = 0; $i < $total_groups; $i++)
	{
		$group_id = (int) $group_rows[$i]['group_id'];
		if ($group_rows[$i]['group_type'] == GROUP_HIDDEN && !$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel') && !in_array($group_id, $in_group[0]))
		{
			continue;
		}

		// Do we have a Group Rank?

		if ($group_rows[$i]['group_rank'])
		{
			if (isset($ranks['special'][$group_rows[$i]['group_rank']]))
			{
				$rank_title = $ranks['special'][$group_rows[$i]['group_rank']]['rank_title'];
			}
			$rank_img = (!empty($ranks['special'][$group_rows[$i]['group_rank']]['rank_image'])) ? '<img src="' . $config['ranks_path'] . '/' . $ranks['special'][$group_rows[$i]['group_rank']]['rank_image'] . '" alt="' . $ranks['special'][$group_rows[$i]['group_rank']]['rank_title'] . '" title="' . $ranks['special'][$group_rows[$i]['group_rank']]['rank_title'] . '" /><br />' : '';
		}
		else
		{
			$rank_title = '';
			$rank_img = '';
		}

		$template->assign_block_vars('groups', array(
			'GROUP_ID'				=> $group_rows[$i]['group_id'],
			'GROUP_NAME'			=> ($group_rows[$i]['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $group_rows[$i]['group_name']] : $group_rows[$i]['group_name'],
			'GROUP_DESC'			=> generate_text_for_display($group_rows[$i]['group_desc'], $group_rows[$i]['group_desc_uid'], $group_rows[$i]['group_desc_bitfield'], $group_rows[$i]['group_desc_options']),
			'GROUP_COLOUR'			=> $group_rows[$i]['group_colour'],
			'GROUP_RANK'			=> $rank_title,

			'RANK_IMG'				=> $rank_img,

			'U_VIEW_GROUP'			=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $group_id),

			'S_SHOW_RANK'			=> true,
		));

		if (!empty($group_leaders[$group_id]))
		{
			foreach($group_leaders[$group_id] as $group_leader)
			{
				$template->assign_block_vars('groups.leaders', array(
					'U_VIEW_PROFILE'			=> $group_leader,
				));
			}
		}

		if (!empty($group_users[$group_id]))
		{
			foreach($group_users[$group_id] as $group_user)
			{
				$template->assign_block_vars('groups.members', array(
					'U_VIEW_PROFILE'			=> $group_user,
				));
			}
		}
	}
}
$db->sql_freeresult($result);

// Set up the Navlinks for the forums navbar
$template->assign_block_vars('navlinks', array(
    'FORUM_NAME' 		=> $user->lang['GROUPS'],
    'U_VIEW_FORUM'  	=> append_sid("{$phpbb_root_path}groups.$phpEx"))
);

// Assign specific vars
$template->assign_vars(array(
	'L_GROUPS_TEXT'		=> $user->lang['GROUPS_TEXT'],
));

// Output page
page_header($user->lang['GROUP_TITLE']);

// Set the template for the page
$template->set_filenames(array(
	'body' => 'groups_body.html')
);

page_footer();

?>