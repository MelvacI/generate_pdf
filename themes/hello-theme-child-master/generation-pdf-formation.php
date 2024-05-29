<?php
require_once 'wp-content/themes/hello-theme-child-master/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdfPath = null; // Initialize $pdfPath to null

/**
 * Generates and stores a PDF file.
 *
 * This function generates a PDF file from the provided content and stores it in the server's file system.
 *
 * @param string $content The content to be included in the PDF.
 * @param string $filename The name of the PDF file.
 * @return string The URL of the generated PDF file.
 */
function generate_and_store_pdf($content, $filename)
{
    $upload_dir = wp_upload_dir();
    $pdf_directory = $upload_dir['basedir'] . '/pdf/';
    if (!file_exists($pdf_directory)) {
        mkdir($pdf_directory, 0755, true);
    }

    $options = new Options();
    $options->set('defaultFont', 'Agenda');
    $options->set('defaultMediaType', 'print');
    $options->set('defaultCSSMediaType', 'print');
    $options->set('isRemoteEnabled', TRUE);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    ob_start();
    include 'wp-content/themes/hello-theme-child-master/css/generation-pdf-formation.css';
    $css = ob_get_clean();
    $dompdf->loadHtml('
    <html>
    <head>
    <style>
    ' . $css . '
    </style>
    </head>
    <body>
    <div class="header"> 
        <div class="logo"><img width="256" height="40" src="https://compagnonsdutourdefrance.tesy2191.odns.fr/wp-content/uploads/2023/10/Logo-complet.svg" alt=""></div> 
                    </div><hr class="orange-hr"/>' . $content . '</body></html>');
    $dompdf->render();

    $pdfContent = $dompdf->output();

    $pdfPath = $pdf_directory . $filename;
    file_put_contents($pdfPath, $pdfContent);
    $pdfUrl = $upload_dir['baseurl'] . '/pdf/' . $filename;

    return $pdfUrl;
}

/**
 * Get the content of the current post and generate a PDF file.
 * 
 * This function retrieves the content of the current post, generates a PDF file from it, and stores it in the server's file system.
 * @return string|null
 */
function get_pdf_content()
{
    global $wpdb;
    $post_id = get_the_ID();
    $pdfPath = null;
    if ($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $pdfFilename = $post->post_title . ".pdf";
            $pdf_directory = wp_upload_dir()['basedir'] . '/pdf/';
            // Check if the PDF already exists and if it is up to date
            if (file_exists($pdf_directory . $pdfFilename)) {
                $file_modified = filemtime($pdf_directory . $pdfFilename);
                $last_modified = $post->post_modified;
                if ($file_modified > strtotime($last_modified)) {
                    $upload_dir = wp_upload_dir();
                    $pdfUrl = $upload_dir['baseurl'] . '/pdf/' . $pdfFilename;
                    $pdfPath = $pdfUrl;
                }
            }

            $post_meta = get_post_meta($post_id);
            $pdfFilename = $post->post_title . ".pdf";
            $term_name = '';
            // Get the term name if it exists
            if (!empty($post_meta['_yoast_wpseo_primary_diplome'][0])) {
                $term_id = $post_meta['_yoast_wpseo_primary_diplome'][0];
                $term = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}terms WHERE term_id = $term_id");
                if ($term) {
                    $term_name = $term->name;
                }
            }
            // Start building the PDF content
            $post_content = '<div class="sub-header">
                <div class="formation-family"><span class="formation-family orange-color formation-family-string">' . $post->post_title . '</span><b class="last-modified">Mise à jour le : ' . date('d/m/Y', strtotime($post->post_modified)) . ' </b>
                </div>
                <h1 class="formation-title">' . $post->post_title . '</h1>
                <div class="formation-diploma orange-color"><b>' . $term_name;

            // Append additional formation information if available
            if ($term_name != "" && $term_name && $post->montrer__cacher_niveau != 'non_visible') {
                $post_content .= ' - ';
            }

            if ($post->montrer__cacher_niveau != 'non_visible') {
                $post_content .= ' Formation Diplômante de Niveau ' . $post->niveau_formation;
            }

            $post_content .= ' </b></div></div>';

            $post_content .= '<div class="formation-content">
            <table>';

            $columns = [];

            // Generate public information section
            $publicInformations = '<div class="formation-public">
                            <h2 class="formation-content-title orange-color">Public / Statut *</h2>
                            ';

            if ($post->public_lyceen == "visible") {
                $publicInformations .= '<span>Lycéen</span><br/><br/>';
            }
            if ($post->public_alternance == "visible") {
                $publicInformations .= '<span>Alternance : <br/>
                    Apprentissage ou contrat de professionnalisation 
                    </span><br/><br/>';
            }
            if ($post->public_salarie == "visible") {
                $publicInformations .= '<span>Salariés dans le cadre : <br/>
            - D’un contrat de transition professionnelle <br/>
            - Du plan de développement des compétences </span><br/><br/>';
            }
            if ($post->public_demandeur == "visible") {
                $publicInformations .= '<span>Demandeurs d’emploi</span>';
            }

            $publicInformations .= '</div>';

            $columns[] = $publicInformations;

            // Generate modalities section if visible
            if ($post->montrer__cacher_modalites == "visible") {
                $modalites = explode("\n", $post->modalites);

                $modalites_list = '<ul>';

                foreach ($modalites as $modalite) {
                    $modalite = str_replace('•', '', $modalite);
                    $modalites_list .= '<li>' . trim($modalite) . '</li>';
                }
                $modalites_list .= '</ul>';

                $columns[] = '<div class="formation-modalites">
                <h2 class="formation-content-title orange-color">Modalités et délais d’accès
                </h2>
                ' . $modalites_list . ' </div>';
            }

            // Generate alternance rhythm section
            $columns[] = '<div class="formation-rythme">
            <h2 class="formation-content-title orange-color">Rythme de l’alternance</h2>
            <p>' . $post->contenu_cursus_formation . '</p> </div>';

            // Generate duration section if visible
            if ($post->montrer__cacher_duree == "visible") {
                $columns[] = '<div class="formation-duree">
                <h2 class="formation-content-title orange-color">Durée</h2>
                <p>' . $post->duree_formation . '</p> </div>';
            }

            // Generate tariffs section if visible
            if ($post->montrer__cacher_tarif == "visible") {
                $columns[] = '<div class="formation-tarif">
                <h2 class="formation-content-title orange-color">Tarifs</h2>
                <p>' . $post->tarif_formation . '</p> </div>';
            }

            // Generate evaluations section if visible
            if ($post->montrer__cacher_evaluations == "visible") {
                $columns[] = '<div class="formation-evaluations">
                <h2 class="formation-content-title orange-color">Modalités d’évaluations</h2>
                <p>' . $post->contenu_evaluations . '</p> </div>';
            }

            // Distribute the columns into rows in the table
            for ($i = 0; $i < count($columns); $i += 3) {
                $post_content .= '<tr>';

                for ($j = 0; $j < 3; $j++) {
                    if (isset($columns[$i + $j])) {
                        $post_content .= '<td class="column">' . $columns[$i + $j] . '</td>';
                    }
                }

                $post_content .= '</tr>';
            }

            $post_content .= '</table>';

            $post_content .= '<hr class="orange-hr"/>
        <span class="public-info">* Sous réserve que soient réunies les conditions nécessaires à la mise en place et/ou la prise en charge de la formation.</span>
        </div>';

            // Generate secondary content sections
            $post_content .= '<div class="formation-secondary-content">
            <div class="orange-color" > Nos formations sont ouvertes aux personnes en situation de handicap : </br>
                Veuillez nous contacter directement afin d’étudier la mise en place de mesures spécifiques pour suivre la formation </div>';

            // Generate prerequisites section
            $post_content .= '<div class="bloc">
            <h2 class="formation-content-title orange-color">' . $post->titre_prerequis_formation . '</h2>
            <p>' . $post->contenu_prerequis . '</p> </div>';

            // Generate job information section
            $post_content .= '<div class="bloc">
            <h2 class="formation-content-title orange-color">Information sur le metier</h2>
            <p>' . $post->contenu_informations_complementaires . '</p> </div>';

            // Generate training objectives section
            $post_content .= '<div class="bloc">
            <h2 class="formation-content-title orange-color">Objectif de la formation</h2>
            <p>' . $post->contenu_objectif_formation . '</p> </div>';

            // Generate skills blocks section if visible
            if ($post->montrer__cacher_blocs_competences == "visible") {
                $post_content .= '<div class="bloc">
                <h2 class="formation-content-title orange-color">Blocs de compétences</h2>
                <p>' . $post->contenu_blocs_competences . '</p> </div>';
            }

            $contenu = $post->contenu_de_la_formation;

            // Remove any inline styles from the content
            $contenu = preg_replace('/style=".*?"/', '', $contenu);

            // Format the content as a list if not already formatted
            if (strpos($contenu, '<ul>') === false || strpos($contenu, '<li') === false) {
                $sections = preg_split('/(<b>.*?<\/b>|<strong>.*?<\/strong>)/', $contenu, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

                $nouveauContenu = '';

                foreach ($sections as $section) {
                    if (strpos($section, '<b>') !== false || strpos($section, '<strong>') !== false) {
                        $nouveauContenu .= $section;
                    } else {
                        $lignes = explode('-', $section);
                        $lignes = array_map(function ($ligne) {
                            return '<li>' . trim($ligne) . '</li>';
                        }, $lignes);
                        $nouveauContenu .= '<ul>' . implode('', $lignes) . '</ul>';
                    }
                }

                $contenu = $nouveauContenu;
            }

            // Add a link to the program if available
            if ($post->montrer__cacher_cta_orange_programme == "visible" && $post->url_cta_orange_programme != "" && $post->label_cta_orange_programme != "") {
                $programLink = $post->url_cta_orange_programme;
                $linkTitle = $post->label_cta_orange_programme;

                $htmlLink = '<span class="progam-link">Pour plus d\'informations, voir le <a href="' . $programLink . '"  target="_blank" >' . $linkTitle . '</a></span>';
                $contenu .= $htmlLink;
            }

            

            // Add the training content section
            $post_content .= '<div class="bloc">
                    <h2 class="formation-content-title orange-color">Contenu de la formation</h2>
                    <p>' . $contenu . '</p> </div>';

            // Add pedagogical methods section if visible
            if ($post->montrer__cacher_methodes_pedagogiques == "visible") {
                $post_content .= '<div class="bloc">
                <h2 class="formation-content-title orange-color">Méthodes pédagogiques</h2>
                <p>' . $post->contenu_methodes_pedagogiques . '</p> </div>';
            }

            // Add pedagogical means section if visible
            if ($post->montrer__cacher_moyens_pedagogiques == "visible") {
                $post_content .= '<div class="bloc">
                <h2 class="formation-content-title orange-color">Moyens pédagogiques</h2>
                <p>' . $post->contenu_moyens_pedagogiques . '</p> </div>';
            }

            // Add additional information section if visible
            if (($post->montrer__cacher_infos_complementaires == "visible") || ($post->montrer__cacher_passerelle_et_equivalences == "visible")) {
                $additionalInformation = '<div class="bloc">
            <h2 class="formation-content-title orange-color">Informations complémentaires</h2>';

                if ($post->montrer__cacher_infos_complementaires == "visible") {
                    $content = $post->contenu_informations_complementaires;
                    $first_bullet = strpos($content, "•");
                    if ($first_bullet !== false) {
                        $before_bullet = substr($content, 0, $first_bullet);
                        $after_bullet = substr($content, $first_bullet + 1);
                        $parts = explode("•", $after_bullet);
                        foreach ($parts as $key => $part) {
                            $part = preg_replace('/[^(\x20-\x7F)]*/', '', $part);
                            $parts[$key] = str_replace("•", "", $part);
                            $parts[$key] = '<li>' . trim($part) . '</li>';
                        }
                        $content = $before_bullet . '<ul>' . implode("", $parts) . '</ul>';
                    } else {
                        $content = '<p>' . $content . '</p>';
                    }

                    $additionalInformation .= $content;
                }

                if ($post->montrer__cacher_passerelle_et_equivalences == "visible") {
                    $additionalInformation .= '<p>' . $post->contenu_passerelle_et_equivalences . '</p>';
                }

                $additionalInformation .= '<p>' . $post->infos_complementaires_formation . '</p>';

                $additionalInformation .= '<br/><p>Pour plus d\'informations, nous contacter.</div>';

                $post_content .= $additionalInformation;
            }

            $post_content .= '<hr class="orange-hr" /><div class="formation-ville">
                    <h2 class="formation-content-title orange-color">Cette formation est proposée sur les villes suivantes :</h2>
                    <table><tr>';

            // Add the cities where the training is available
            $etablissements = wp_get_post_terms($post->ID, 'etablissement', array("fields" => "slugs"));

            $sites = get_sites();

            $sites = array_filter($sites, function ($site) use ($etablissements) {
                explode('.', $site->domain);
                $site_slug = explode('.', $site->domain)[0];
                return in_array($site_slug, $etablissements);
            });

            $i = 0;
            foreach ($sites as $site) {
                // Switch to the site to get the site information
                switch_to_blog($site->blog_id);
                $siteInfo = [
                    'name' => get_field('field_654cc23c9fb0c', 'option'),
                    'url' => get_site_url(),
                    'adress' => get_field('field_654cc3ed3e752', 'option'),
                ];
                $cityBlock = '<td><div class="ville">
                <div class="ville-title">' . $siteInfo["name"] . '</div>
                <p> ' . $siteInfo["adress"]["street"]  . '</p>
                <p> ' . $siteInfo["adress"]["city"]  . '</p></div></td>';

                if ($i % 2 == 0 && $i != 0) {
                    $post_content .= '</tr><tr>';
                }

                $post_content .= $cityBlock;
                restore_current_blog();
                $i++;
            }

            $post_content .= '</tr></table></div>';

            // Generate the PDF if it does not already exist
            if ($pdfPath == null) {
                $pdfPath = generate_and_store_pdf($post_content, $pdfFilename);
            }

            return $pdfPath;
        } else {
            // Log an error if the post content is empty or not found
            error_log("PDF Generation: The page content is empty or not found.");
        }
    } else {
        // Log an error if the current post ID is not available
        error_log("PDF Generation: Unable to retrieve the current post ID.");
    }

    return null;
}
$pdfPath = get_pdf_content();


?>

<!-- Add this code to the Elementor template where you want to display the button -->
<?php if ($pdfPath) : ?>
    <a class="elementor-button elementor-button-link elementor-size-sm" style="width:100%;" href="<?php echo esc_url($pdfPath); ?>" target="_blank">
        <span class="elementor-button-content-wrapper">
            <span class="elementor-button-text">Télécharger le PDF</span>
        </span>
    </a>
<?php endif; ?>