<?php
/**
 * Generate PDF from Q&A Markdown Document
 */

// Read the markdown file
$markdown = file_get_contents('TeleHealth_QA_Document.md');

// Simple markdown to HTML conversion
$html = convertMarkdownToHtml($markdown);

function convertMarkdownToHtml($md) {
    // Convert headers
    $md = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $md);
    $md = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $md);
    $md = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $md);

    // Convert bold
    $md = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $md);

    // Convert code blocks
    $md = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $md);
    $md = preg_replace('/`(.*?)`/', '<code>$1</code>', $md);

    // Convert links
    $md = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $md);

    // Convert line breaks
    $md = preg_replace('/\n\n/', '</p><p>', $md);
    $md = preg_replace('/\n/', '<br>', $md);

    // Convert lists
    $md = preg_replace('/^- (.*?)$/m', '<li>$1</li>', $md);
    $md = preg_replace('/^(\d+)\. (.*?)$/m', '<li>$2</li>', $md);

    // Wrap in paragraphs
    $md = '<p>' . $md . '</p>';

    return $md;
}

// Create HTML document
$htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>TeleHealth Platform - Q&A Documentation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            page-break-before: always;
        }
        h1:first-of-type {
            page-break-before: auto;
        }
        h2 {
            color: #3498db;
            margin-top: 30px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 5px;
        }
        h3 {
            color: #555;
            margin-top: 20px;
        }
        p {
            margin: 10px 0;
        }
        strong {
            color: #2c3e50;
        }
        code {
            background-color: #f8f8f8;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        pre {
            background-color: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
            overflow-x: auto;
        }
        pre code {
            background: none;
            padding: 0;
        }
        ul, ol {
            margin: 10px 0;
            padding-left: 30px;
        }
        li {
            margin: 5px 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        .toc {
            background-color: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        @media print {
            body {
                max-width: 100%;
            }
            h1 {
                page-break-before: always;
            }
            h1:first-of-type {
                page-break-before: auto;
            }
        }
    </style>
</head>
<body>
    $html
</body>
</html>
HTML;

// Output as HTML for browser viewing
header('Content-Type: text/html; charset=UTF-8');
echo $htmlContent;

// To save as PDF, you can:
// 1. Print this page as PDF from browser (Ctrl+P)
// 2. Or use wkhtmltopdf command line tool
// 3. Or install a PHP PDF library like TCPDF or MPDF

?>