<?php
/*
Plugin Name: © Aftermath Cleaner
Description: Search and clean malicious content
Author: RAPHAEL CORNUZ
Version: 0.7.4
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

		echo '<h2>Posts : ' . number_format($total_posts, 0, '.', '\'') . '</h2>';

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
			echo '<b style="color:red;">' . number_format($total_posts_without_author_name, 0, '.', '\'') . '</b>';
		} else {
			echo number_format($total_posts_without_author_name, 0, '.', '\'');
		}
		echo '</p>';

		?>
		<script type='text/javascript'>
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

						function formatNumberWithApostrophe(x) {
    					return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "'");
						}

						// Start updating the total posts every half second
						var intervalId = setInterval(function() {
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'get_total_posts_by_user',
									user_id: userId
								},
								success: function(response) {
									var formattedResponse = formatNumberWithApostrophe(parseInt(response));
									$('#total-posts-' + userId).html(formattedResponse); // Update the total posts cell
								}
							});
						}, 500);

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
		</script>
		<?php

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
			TABLE.aftermath_posts,
			TABLE.aftermath_keywords {
				width: calc(100% - 15px);
				border: 0;
				margin: 0;
				padding: 15px;
				background: white;
				border-radius: 5px;
				box-shadow: 2px 4px 10px -7px black;
			}
			TABLE.aftermath_posts TR TD,
			TABLE.aftermath_keywords TR TD {
				padding: 2px .5em;
				border: 0;
			}
			TABLE.aftermath_posts TH,
			TABLE.aftermath_keywords TH {
				text-align: left;
				font-size: smaller;
				border-bottom: 1px solid lightgrey;
				padding-bottom: 5px;
			}
			TABLE.aftermath_posts TR:hover TD,
			TABLE.aftermath_keywords TR:hover TD {
				background-color: lightsalmon;
			}

			BUTTON.delete-posts:hover {
				cursor: pointer;
			}
		</style>
		<table class="aftermath_posts" cellspacing=0 cellpadding=0>
			<tr>
				<th>Author Name</th>
				<th>Role</th>
				<th>Author ID</th>
				<th>Total Posts</th>
				<th>First Post</th>
				<th>Last Post</th>
				<th>Action</th>
			</tr>
			<?php
			foreach ($posts as $post) {
				// Récupérer le rôle de l'utilisateur
				$user = new WP_User($post->user_id);
				$role = !empty($user->roles) ? '<small>' . $user->roles[0] . '</small>' : '<span class="dashicons dashicons-warning" style="color:red;"></span> <small>[empty]</small>';

				// Si le nom d'utilisateur est vide, définir le fond en jaune
				$background = empty($post->user_name) ? ' style="background-color: linen;"' : '';

				echo '<tr' . $background . '>';
				echo '<td>' . (!empty($post->user_name) ? '<b>' . $post->user_name . '</b>' : '<span class="dashicons dashicons-warning" style="color:red;"></span> <small>[empty]</small>') . '</td>';
				echo '<td>' . $role . '</td>';
				echo '<td>' . $post->user_id . '</td>';
				echo '<td id="total-posts-' . $post->user_id . '">' . number_format($post->total_posts, 0, '.', '\'') . '</td>';
				echo '<td>' . date('Y/m/d @H:i', strtotime($post->first_post)) . '</td>';
				echo '<td>' . date('Y/m/d @H:i', strtotime($post->last_post)) . '</td>';
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

			echo '<br/><hr/>';

			/* BAD KEYWORDS
		------------------------------------------------------------- */
			echo '<h2>Malicious content</h2>';

			// Définissez les termes de recherche par défaut
			$default_search_term = 'casino,viagra,bitcoin,cialis,porn,sex,';

			// Récupérez le terme de recherche soumis, s'il existe
			$search_term = isset($_POST['search_term']) ? $_POST['search_term'] : $default_search_term;

			// Affichez le formulaire de recherche
			?>
			<form method="post">
				<input type="text" name="search_term" value="<?php echo $search_term; ?>" style="width:360px;">
				<button type="submit">
					<span class="dashicons dashicons-search"></span> Search
				</button>
			</form>
			<?php

			// Exécutez le code de recherche avec le terme de recherche
			$search_terms = explode(',', $search_term);
			$search_queries = array();

			foreach ($search_terms as $term) {
				$term = trim($term);
				if (!empty($term)) { // Ignore les termes vides
					$term = $wpdb->esc_like($term);
					$search_queries[] = "post_content LIKE '% " . $term . " %'";
				}
			}
			$search_query = implode(' OR ', $search_queries);

			// Exécutez la requête SQL pour rechercher le terme dans tous les posts
			$results = $wpdb->get_results("SELECT ID, post_title, post_type, post_author, post_date
				FROM $wpdb->posts
				WHERE (post_type = 'post' OR post_type = 'page') AND ($search_query)
			");

			// Obtenez le nombre de résultats
			$num_results = count($results);

			// Affichez les résultats
			if (!empty($results)) {
				echo '<h4>' . number_format($num_results, 0, '.', '\'') . ' Search results for : <span style="color:red;">' . implode(', ', $search_terms) . '</span></h4>';
				echo '<table class="aftermath_keywords" cellspacing=0 cellpadding=0>';
				echo '<tr><th>Type</th><th>Title</th><th>Author Name</th><th>Date</th><th>Action</th></tr>';
				foreach ($results as $result) {
					$author_info = get_userdata($result->post_author);
					$author_name = !empty($author_info->user_nicename) ? '<b>'.$author_info->user_nicename.'</b>' : '<span class="dashicons dashicons-warning" style="color:red;"></span> <small>[empty]</small>';
					$post_link = get_permalink($result->ID);
					echo '<tr>';
					echo '<td>' . ucfirst($result->post_type) . '</td>';
					echo '<td><a href="' . $post_link . '" target="_blank">' . $result->post_title . '</a></td>';
					echo '<td>' . $author_name . '</td>';
					echo '<td>' . date('Y/m/d @H:i', strtotime($result->post_date)) . '</td>';
					echo '<td><button type="button" class="clean-content" data-content-id="' . $result->ID . '" data-content-title="' . $result->post_title . '"><span class="dashicons dashicons-superhero"></span> Clean</button></td>';
					echo '</tr>';
				}
				echo '</table>';
				//var_dump($results);
			} else {
				echo '<p>No results found for keywords : "' . implode(', ', $search_terms) . '"</p>';
			}

			?>
			<script type='text/javascript'>
				jQuery(document).ready(function($) {
					$('.clean-content').click(function() {
						var postId = $(this).data('content-id');
						var postTitle = $(this).data('content-title');
						if (confirm('Are you sure you want to delete the content with the title : "' + postTitle + '" ?')) {
							$.ajax({
								url: ajaxurl, // Vous devez définir cette variable globale dans votre fichier PHP
								type: 'POST',
								data: {
									action: 'clean_content',
									post_id: postId
								},
								success: function(response) {
									alert('The content has been successfully deleted.');
									location.reload(); // Refresh the page
								},
								error: function() {
									alert('An error occurred during deletion.');
								}
							});
						}
					});
				});
			</script>
		<?php


	} else {
		echo '<p>Sorry, you do not have the necessary permissions to access this page.</p>';
	}
}

//
add_action('wp_ajax_delete_posts_by_user', 'delete_posts_by_user');
function delete_posts_by_user() {
	// Check if the current user is an administrator
	if (!current_user_can('administrator')) {
		wp_die('You are not authorized to perform this action.'); // This is required to terminate immediately and return a proper response
	}
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

//
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

//
add_action('wp_ajax_clean_content', 'clean_content');
function clean_content() {
	global $wpdb;
	$post_id = intval($_POST['post_id']);
	$wpdb->update(
		$wpdb->posts,
		array('post_content' => ''), // Mettez à jour le contenu du post pour le rendre vide
		array('ID' => $post_id), // Où le ID du post est égal à $post_id
		array('%s'), // Le format du contenu du post
		array('%d') // Le format de l'ID du post
	);
	wp_die(); // Cette fonction est requise pour terminer correctement la requête AJAX
}
