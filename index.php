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
		$total_posts = $wpdb->get_var("SELECT COUNT(*) FROM wp_posts WHERE post_status = 'publish'");

		echo '<h2>Posts</h2>';
		echo '<p>Total posts : <b>' . $total_posts . '</b></p>';

		$posts = $wpdb->get_results("
    SELECT
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
        p.post_status = 'publish'
    GROUP BY
        p.post_author
");
		echo '<table border="0" style="width: calc(100% - 15px); border: 0; margin: 0; padding: 15px; background: white; border-radius: 5px; box-shadow: 2px 4px 10px -7px black;">';
		echo '<tr><th>Author Name</th><th>Role</th><th>Author ID</th><th>Total Posts</th><th>First Post</th><th>Last Post</th></tr>';
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
			echo '<td>' . $post->total_posts . '</td>';
			echo '<td>' . date('d.m.Y @H:i', strtotime($post->first_post)) . '</td>';
			echo '<td>' . date('d.m.Y @H:i', strtotime($post->last_post)) . '</td>';
			echo '</tr>';
		}
		echo '</table>';

		// Récupérer le nombre total d'articles sans nom d'auteur
		$total_posts_without_author_name = $wpdb->get_var("
        SELECT COUNT(*)
        FROM wp_posts p
        LEFT JOIN wp_users u ON p.post_author = u.ID
        WHERE p.post_status = 'publish' AND (u.user_login IS NULL OR u.user_login = '')
    ");

		echo '<p>Anonymous posts : <b style="color:red;">' . $total_posts_without_author_name . '</b></p>';
	} else {
		echo '<p>Sorry, you do not have the necessary permissions to access this page.</p>';
	}
}
