<?php
global $ws_logs;
$ws_root_url = ws_root_url();
?>
<h1><a href="<?php echo $ws_root_url; ?>"><?php echo $ws_root_url; ?></a></h1>
<h2><?php _e('Refreshing website HTML cache'); ?></h2>
<?php

?>
<?php
// Specify the URL of the HTML webpage
$url = "https://www.example.com/page.html";  // Replace with the actual URL

// Fetch the HTML content from the URL
$html_content = file_get_contents($url);

// Check if the content was retrieved successfully
if ($html_content === false) {
    die("Error: Failed to fetch HTML content from URL.");
}

// Specify the filename for the saved HTML file
$filename = "saved_page.html";  // You can customize the filename

// Save the HTML content to the file
file_put_contents($filename, $html_content);

// Provide feedback to the user
echo "HTML file saved successfully as $filename";
?>