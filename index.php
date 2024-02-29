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
		echo '<p>Welcome to the Aftermath Cleaner page.</p>';

		// Ajoutez votre code de nettoyage ici
	} else {
		echo '<p>Sorry, you do not have the necessary permissions to access this page.</p>';
	}
}
