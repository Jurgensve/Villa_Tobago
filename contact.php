<?php
// contact.php - Handles form submissions natively

// Configure your email address here
$to_email = 'info@villatobago.co.za'; 
$subject = 'New Contact Form Message - Villa Tobago Website';

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $name = filter_var(trim($_POST["name"]), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $message = filter_var(trim($_POST["message"]), FILTER_SANITIZE_STRING);

    // Basic validation
    if (empty($name) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Handle invalid input
        http_response_code(400);
        echo "Please complete all fields and provide a valid email address.";
        exit;
    }

    // Prepare email content
    $email_content = "Name: $name\n";
    $email_content .= "Email: $email\n\n";
    $email_content .= "Message:\n$message\n";

    // Prepare email headers
    $headers = "From: $name <$email>";

    // Send the email
    if (mail($to_email, $subject, $email_content, $headers)) {
        // Success
        http_response_code(200);
        echo "Thank you! Your message has been sent.";
    } else {
        // Server error
        http_response_code(500);
        echo "Oops! Something went wrong and we couldn't send your message.";
    }

} else {
    // Not a POST request
    http_response_code(403);
    echo "There seeems to be a problem with your submission, please try again.";
}
?>
