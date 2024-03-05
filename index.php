<?php
/*
Plugin Name: © Aftermath Cleaner
Description: Wordpress extension to analyze and clean a corrupted site
Author: RAPHAEL CORNUZ
Version: 1.1
*/

// Ajoutez une nouvelle page dans le panneau d'administration sous l'onglet "Settings"
function aftermath_cleaner_menu() {
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
function aftermath_cleaner_page() {
	// Vérifiez si l'utilisateur actuel est un administrateur
	if (current_user_can('administrator')) {
		echo '<br><h1><span class="dashicons dashicons-superhero"></span> Aftermath Cleaner</h1>';
		global $wpdb;

		/* POSTS
		------------------------------------------------------------- */
		// Récupérer le nombre total d'articles
		$total_posts = $wpdb->get_var("SELECT COUNT(*)
			FROM wp_posts
			WHERE (post_status = 'publish' OR post_status = 'draft' OR post_status = 'trash')
			AND post_type = 'post'
		");

		echo '<h2>Posts : ' . $total_posts . '</h2>';

		// Récupérer le nombre total d'articles sans nom d'auteur
		$total_posts_without_author_name = $wpdb->get_var("SELECT COUNT(*) FROM wp_posts p
			LEFT JOIN wp_users u ON p.post_author = u.ID
			WHERE
				(	p.post_status = 'publish' OR p.post_status = 'draft' OR p.post_status = 'trash')
				AND (u.user_login IS NULL OR u.user_login = '')
				AND p.post_type = 'post'
		");

		echo '<p>Anonymous posts : ';
		if ($total_posts_without_author_name > 0) {
			echo '<b style="color:red;">' . $total_posts_without_author_name . '</b>';
		} else {
			echo $total_posts_without_author_name;
		}
		echo '</p>';

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

		$posts = $wpdb->get_results("SELECT
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
		");
		?>
		<style>
			TABLE.aftermath_posts {
				width: calc(100% - 15px);
				border: 0;
				margin: 0; padding: 15px;
				background: white;
				border-radius: 5px;
				box-shadow: 2px 4px 10px -7px black;
			}
			TABLE.aftermath_posts TH {
				text-align: left; font-size: smaller;
				border-bottom: 1px solid lightgrey;
				padding-bottom: 5px;
			}
			TABLE.aftermath_posts TR:hover TD {
				background-color: red; color:white;
			}
			BUTTON.delete-posts:hover {
				cursor:pointer;
			}
		</style>
		<table class="aftermath_posts">
			<tr>
				<th>Author Name</th><th>Role</th><th>Author ID</th><th>Total Posts</th><th>First Post</th><th>Last Post</th><th>Action</th>
			</tr>
		<?php
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
			echo '<td>';
			if (empty($post->user_name) || empty($role)) {
				echo '<button type="button" class="delete-posts" data-user-id="' . $post->user_id . '"><span class="dashicons dashicons-superhero"></span> Clean</button>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</table><br/>';

		echo '<br/><hr/>';

		/* CATEGORIES
		------------------------------------------------------------- */
		// Obtenez toutes les catégories
		$all_categories = get_terms('category', array(
			'hide_empty' => false,
		));
		// Obtenez toutes les catégories utilisées
		$used_categories = get_terms('category', array(
			'hide_empty' => true,
		));
		// Calculez le nombre de catégories non utilisées
		$unused_categories_count = count($all_categories) - count($used_categories);

		echo '<h3>Categories : ' . count($all_categories) . '</h3>';
		echo '<p>Unused categories : ';
		// Affichez le total de catégories, le nombre de catégories utilisées et le bouton pour effacer les catégories inutilisées
		if ($unused_categories_count > 1) {
			echo '<b style="color:red;">' . $unused_categories_count . '</b>';
			echo '<button id="delete-unused-categories" style="margin-left: 1em; cursor:pointer;"><span class="dashicons dashicons-superhero"></span> Clean</button>';
		} else {
			echo $unused_categories_count;
		}
		echo '<br/><small><em>* The default Wordpress category is never deleted, even if it is not used.</em></small></p>';
		// Ajoutez du JavaScript pour gérer le clic sur le bouton
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#delete-unused-categories').click(function() {
					var data = {
						'action': 'delete_unused_categories'
					};
					$.post(ajaxurl, data, function(response) {
						alert('Unused categories deleted : ' + response);
						location.reload(); // Refresh the page
					});
				});
			});
		</script>
		<?php

		echo '<br/><hr/>';


		/* TAGS
		------------------------------------------------------------- */
		// Obtenez le nombre total de tags
		$all_tags = get_terms('post_tag', array(
			'hide_empty' => false,
		));
		// Obtenez tous les tags utilisés
		$used_tags = get_terms('post_tag', array(
			'hide_empty' => true,
		));
		// Calculez le nombre de tags non utilisés
		$unused_tags_count = count($all_tags) - count($used_tags);

		echo '<h3>Tags : ' . count($all_tags) . '</h3>';
		echo '<p>Unused tags : ';
		if ($unused_tags_count > 0) {
			echo '<b style="color:red;">' . $unused_tags_count . '</b>';
			echo '<button id="delete-unused-tags" style="margin-left: 1em; cursor:pointer;"><span class="dashicons dashicons-superhero"></span> Clean</button>';
		} else {
			echo $unused_tags_count;
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

		echo '<br/><hr/>';


		/* PAGES
		------------------------------------------------------------- */
		// Obtenez le nombre total de pages
		$pages_count = wp_count_posts('page')->publish;
		// Obtenez le nombre total de pages en brouillon
		$draft_pages_count = wp_count_posts('page')->draft;
		// Obtenez le nombre total de pages privées
		$private_pages_count = wp_count_posts('page')->private;

		$total_pages_count = $pages_count + $draft_pages_count + $private_pages_count;
		echo '<h2>Pages : ' . $total_pages_count . '</h2>';
		echo '<p>Published (' . $pages_count . ') &nbsp;|&nbsp; Drafts (' . $draft_pages_count . ') &nbsp;|&nbsp; Private (' . $private_pages_count . ')</p>';

		// Récupérer le nombre total de pages sans nom d'auteur
		$total_pages_without_author_name = $wpdb->get_var("SELECT COUNT(*) FROM wp_posts p
		LEFT JOIN wp_users u ON p.post_author = u.ID
		WHERE
			(	p.post_status = 'publish' OR p.post_status = 'draft' OR p.post_status = 'private')
			AND (u.user_login IS NULL OR u.user_login = '')
			AND p.post_type = 'page'
		");
		echo '<p>Anonymous pages : ';
		if ($total_pages_without_author_name > 0) {
			echo '<b style="color:red;">' . $total_pages_without_author_name . '</b>';
		} else {
			echo $total_pages_without_author_name;
		}
		echo '</p>';

	} else {
		echo '<p>Sorry, you do not have the necessary permissions to access this page.</p>';
	}
}


add_action('wp_ajax_delete_posts_by_user', 'delete_posts_by_user');
function delete_posts_by_user() {
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
function get_total_posts_by_user() {
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
function delete_unused_tags() {
	global $wpdb;
	// Obtenez tous les tags inutilisés
	$unused_tags = $wpdb->get_results("SELECT term_id
		FROM $wpdb->term_taxonomy
		WHERE taxonomy = 'post_tag'
		AND count = 0
	");
	// Supprimez tous les tags inutilisés
	foreach ($unused_tags as $tag) {
		wp_delete_term($tag->term_id, 'post_tag');
	}
	echo count($unused_tags);
	wp_die();
}

// Ajoutez une action AJAX pour effacer les catégories inutilisées
add_action('wp_ajax_delete_unused_categories', 'delete_unused_categories');
function delete_unused_categories() {
	global $wpdb;
	// Obtenez l'ID de la catégorie par défaut
	$default_category_id = get_option('default_category');
	// Obtenez toutes les catégories inutilisées, à l'exception de la catégorie par défaut
	$unused_categories = $wpdb->get_results("SELECT term_id
		FROM $wpdb->term_taxonomy
		WHERE taxonomy = 'category'
		AND count = 0
		AND term_id != $default_category_id
	");
	// Supprimez toutes les catégories inutilisées
	foreach ($unused_categories as $category) {
		wp_delete_term($category->term_id, 'category');
	}
	echo count($unused_categories);
	wp_die();
}