<?php
/*
Plugin Name: © Aftermath Cleaner
Description: Wordpress extension to analyze and clean a corrupted site
Author: RAPHAEL CORNUZ
Version: 1.0
*/

// Ajoutez une nouvelle page dans le panneau d'administration sous l'onglet "Settings"
function aftermath_cleaner_menu()
{
	add_options_page(
		'© Aftermath Cleaner', // Titre de la page
		'© Aftermath Cleaner', // Titre du menu
		'manage_options', // Capacité requise pour voir le menu
		'aftermath-cleaner', // Slug du menu
		'aftermath_cleaner_page' // Fonction pour afficher la page
	);
}
add_action('admin_menu', 'aftermath_cleaner_menu');

// Fonction pour afficher la page
function aftermath_cleaner_page()
{
	// Vérifiez si l'utilisateur actuel est un administrateur
	if (current_user_can('administrator')) {
		echo '<h1>© Aftermath Cleaner</h1>';
		global $wpdb;

		// Récupérer le nombre total d'articles
		$total_posts = $wpdb->get_var(
			"SELECT COUNT(*) FROM wp_posts
			WHERE (post_status = 'publish' OR post_status = 'draft' OR post_status = 'trash')
			AND post_type = 'post'
		"
		);
		echo '<style>TABLE.aftermath_posts TR:hover TD { background-color: red; color:white; } BUTTON.delete-posts:hover { cursor:pointer; }</style>';
		echo '<h2>Posts</h2>';
		echo '<p>Total Posts : <b>' . $total_posts . '</b></p>';

		$posts = $wpdb->get_results(
			"SELECT
			p.post_author as user_id,
			u.user_login as user_name,
			COUNT(p.ID) as total_posts,
			MIN(p.post_date) as first_post,
			MAX(p.post_date) as last_post
		FROM
			wp_posts p
		LEFT JOIN
			wp_users u ON p.post_author = u.ID
		WHERE
			(p.post_status = 'publish' OR p.post_status = 'draft' OR p.post_status = 'trash')
			AND p.post_type = 'post'
		GROUP BY
			p.post_author
		"
		);

		echo '<table class="aftermath_posts" border="0" style="width: calc(100% - 15px); border: 0; margin: 0; padding: 15px; background: white; border-radius: 5px; box-shadow: 2px 4px 10px -7px black;">';
		echo '<tr><th>Author Name</th><th>Role</th><th>Author ID</th><th>Total Posts</th><th>First Post</th><th>Last Post</th><th>Action</th></tr>';
		foreach ($posts as $post) {
			// Récupérer le rôle de l'utilisateur
			$user = new WP_User($post->user_id);
			$role = !empty($user->roles) ? '<small>' . $user->roles[0] . '</small>' : '⚠ <small>[empty]</small>';

			// Si le nom d'utilisateur est vide, définir le fond en jaune
			$background = empty($post->user_name) ? ' style="background-color: linen;"' : '';

			echo '<tr' . $background . '>';
			echo '<td>' . (!empty($post->user_name) ? '<b>' . $post->user_name . '</b>' : '⚠ <small>[empty]</small>') . '</td>';
			echo '<td>' . $role . '</td>';
			echo '<td>' . $post->user_id . '</td>';
			echo '<td id="total-posts-' . $post->user_id . '">' . $post->total_posts . '</td>';
			echo '<td>' . date('d.m.Y @H:i', strtotime($post->first_post)) . '</td>';
			echo '<td>' . date('d.m.Y @H:i', strtotime($post->last_post)) . '</td>';
			echo '<td><button type="button" class="delete-posts" data-user-id="' . $post->user_id . '">Delete Posts</button></td>';
			echo '</tr>';
		}
		echo '</table>';

		// Récupérer le nombre total d'articles sans nom d'auteur
		$total_posts_without_author_name = $wpdb->get_var("SELECT COUNT(*) FROM wp_posts p
			LEFT JOIN wp_users u ON p.post_author = u.ID
			WHERE
				(	p.post_status = 'publish' OR p.post_status = 'draft' OR p.post_status = 'trash')
				AND (u.user_login IS NULL OR u.user_login = '')
				AND p.post_type = 'post'
		");


		echo '<p>Anonymous posts : <b style="color:red;">' . $total_posts_without_author_name . '</b></p>';

		echo "<script type='text/javascript'>
			jQuery(document).ready(function($) {
				$('.delete-posts').click(function() {
					var userId = $(this).data('user-id');
					var confirmation = confirm('Are you sure you want to delete all posts from Author ID ' + userId + '?');
					if (confirmation) {
						var button = $(this);
						button.html('Deleting...'); // Change the button text
						button.prop('disabled', true); // Disable the button
						$('#total-posts-' + userId).css({
							'background-color': 'red',
							'color': 'white',
							'font-weight': 'bold'
						}); // Change the styles of the total posts cell

						// Start updating the total posts every second
						var intervalId = setInterval(function() {
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'get_total_posts_by_user',
									user_id: userId
								},
								success: function(response) {
									$('#total-posts-' + userId).html(response); // Update the total posts cell
								}
							});
						}, 1000);

						// Start the deletion process
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'delete_posts_by_user',
								user_id: userId
							},
							success: function(response) {
								clearInterval(intervalId); // Stop updating the total posts
								alert('All posts from Author ID ' + userId + ' have been deleted.');
								location.reload(); // Refresh the page
							},
							error: function(jqXHR, textStatus, errorThrown) {
								clearInterval(intervalId); // Stop updating the total posts
								alert('An error has occurred: ' + textStatus + ' ' + errorThrown);
								location.reload(); // Refresh the page
							}
						});

					}
				});
			});
		</script>";

		echo '<hr/>';
		echo '<h4>Tags</h4>';

		// Obtenez le nombre total de tags pour post_type=post
		$all_tags = get_terms('post_tag', array(
			'hide_empty' => false,
		));
		// Obtenez tous les tags utilisés
		$used_tags = get_terms('post_tag', array(
			'hide_empty' => true,
		));
		// Calculez le nombre de tags non utilisés
		$unused_tags_count = count($all_tags) - count($used_tags);

		echo '<p>Total Tags : <b>' . count($all_tags) . '</b> ( ' . count($used_tags) . ' used ).';
		if ($unused_tags_count > 0) {
			echo '<button id="delete-unused-tags">Delete <b>' . $unused_tags_count . '</b> unused tags</button>';
		}
		echo '</p>';
		// Ajoutez du JavaScript pour gérer le clic sur le bouton
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#delete-unused-tags').click(function() {
					var data = {
						'action': 'delete_unused_tags'
					};
					$.post(ajaxurl, data, function(response) {
						alert('Unused tags deleted : ' + response);
						location.reload(); // Refresh the page
					});
				});
			});
		</script>
		<?php

		echo '<hr/>';
	} else {
		echo '<p>Sorry, you do not have the necessary permissions to access this page.</p>';
	}
}


add_action('wp_ajax_delete_posts_by_user', 'delete_posts_by_user');
function delete_posts_by_user()
{
	// Check if the current user is an administrator
	if (!current_user_can('administrator')) {
		wp_die('Vous n\'êtes pas autorisé à effectuer cette action.'); // This is required to terminate immediately and return a proper response
	}
	/*
		$userId = intval($_POST['user_id']);
		$args = array(
			'author' => $userId,
			'post_type' => 'post',
			'post_status' => 'any', // This will retrieve all posts by the user, regardless of post status
			'posts_per_page' => -1 // This will retrieve all posts by the user
		);
		$userPosts = get_posts($args);

		foreach ($userPosts as $userPost) {
			wp_delete_post($userPost->ID); // Move to trash
			wp_delete_post($userPost->ID, true); // Set second parameter to true if you want to force delete
		}
		echo count($userPosts); // This will return the number of posts deleted
		wp_die(); // This is required to terminate immediately and return a proper response
	*/
	global $wpdb;
	$userId = intval($_POST['user_id']);
	// Get all post IDs for the user, regardless of post status
	$postIds = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type = 'post'", $userId));
	foreach ($postIds as $postId) {
		wp_delete_post($postId, true); // Delete each post permanently
	}
	echo count($postIds); // This will return the number of posts deleted
	wp_die(); // This is required to terminate immediately and return a proper response
}

add_action('wp_ajax_get_total_posts_by_user', 'get_total_posts_by_user');
function get_total_posts_by_user()
{
	$userId = intval($_POST['user_id']);
	$args = array(
		'author' => $userId,
		'post_type' => 'post',
		'posts_per_page' => -1 // This will retrieve all posts by the user
	);
	$userPosts = get_posts($args);
	echo count($userPosts); // This will return the number of posts
	wp_die(); // This is required to terminate immediately and return a proper response
}



// Ajoutez une action AJAX pour effacer les tags inutilisés
add_action('wp_ajax_delete_unused_tags', 'delete_unused_tags');
function delete_unused_tags()
{
	$all_tags = get_terms('post_tag', array(
		'hide_empty' => false,
	));
	$used_tags = get_terms('post_tag', array(
		'hide_empty' => true,
	));
	$unused_tags = array_diff($all_tags, $used_tags);
	foreach ($unused_tags as $tag) {
		wp_delete_term($tag->term_id, 'post_tag');
	}
	echo count($unused_tags);
	wp_die();
}
