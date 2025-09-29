<?php

// Create downloads directory
function createDownloadsDirectory() {
    $downloads_dir = __DIR__ . '/downloads';
    
    if (!file_exists($downloads_dir)) {
        mkdir($downloads_dir, 0755, true);
    }
    
    return $downloads_dir;
}

// Extract Snapchat info using yt-dlp
function extractSnapchatInfo($url) {
    $command = "yt-dlp --quiet --no-warnings --dump-json " . escapeshellarg($url) . " 2>&1";
    $output = shell_exec($command);
    
    if (empty($output)) {
        throw new Exception("Failed to extract video information. Make sure yt-dlp is installed.");
    }
    
    $info = json_decode($output, true);
    
    if (!$info) {
        throw new Exception("Invalid video information received: " . $output);
    }
    
    return array(
        'title' => isset($info['title']) ? $info['title'] : 'Snapchat Story',
        'thumbnail' => isset($info['thumbnail']) ? $info['thumbnail'] : '',
        'id' => uniqid(),
        'original_url' => $url
    );
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'preview':
            try {
                $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
                
                if (empty($url)) {
                    throw new Exception('URL is required');
                }
                
                $video_data = extractSnapchatInfo($url);
                echo json_encode(['success' => true, 'data' => $video_data]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'download':
            try {
                $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
                
                if (empty($url)) {
                    throw new Exception('URL is required');
                }
                
                $downloads_dir = createDownloadsDirectory();
                $filename = uniqid() . '.mp4';
                $filepath = $downloads_dir . '/' . $filename;
                
                $command = sprintf(
                    'yt-dlp --format "bestvideo+bestaudio/best" --merge-output-format mp4 --output %s --quiet --no-warnings %s 2>&1',
                    escapeshellarg($filepath),
                    escapeshellarg($url)
                );
                
                $result = shell_exec($command);
                
                if (!file_exists($filepath)) {
                    throw new Exception('Failed to download video: ' . $result);
                }
                
                // Return download URL
                $download_url = 'downloads/' . $filename;
                
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'download_url' => $download_url,
                        'filename' => 'snapchat_story.mp4'
                    ]
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Handle streaming
if (isset($_GET['action']) && $_GET['action'] === 'stream') {
    $vid = $_GET['vid'] ?? '';
    $url = $_GET['url'] ?? '';
    
    if (empty($vid) || empty($url)) {
        die('Invalid parameters');
    }
    
    $downloads_dir = createDownloadsDirectory();
    $temp_path = $downloads_dir . '/' . $vid . '.mp4';
    
    if (!file_exists($temp_path)) {
        // Download the video
        $command = sprintf(
            'yt-dlp --format "bestvideo+bestaudio/best" --merge-output-format mp4 --output %s --quiet --no-warnings %s 2>&1',
            escapeshellarg($temp_path),
            escapeshellarg($url)
        );
        
        $result = shell_exec($command);
        
        if (!file_exists($temp_path)) {
            die('Failed to download video: ' . $result);
        }
    }
    
    // Stream the file
    header('Content-Type: video/mp4');
    header('Content-Length: ' . filesize($temp_path));
    header('Accept-Ranges: bytes');
    
    readfile($temp_path);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snapchat Downloader - PHP Test</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #121212;
            color: #f1f1f1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        h1 {
            color: #63b7a8;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        
        input[type="text"] {
            width: 60%;
            max-width: 500px;
            padding: 12px 15px;
            border-radius: 8px;
            border: none;
            outline: none;
            font-size: 1rem;
            margin-bottom: 10px;
            background: #1e1e1e;
            color: #f1f1f1;
            border: 1px solid #333;
        }
        
        button {
            background-color: #63b7a8;
            color: #121212;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 5px;
            transition: background-color 0.2s;
        }
        
        button:hover {
            background-color: #4a8a7a;
        }
        
        #preview {
            margin-top: 30px;
            text-align: center;
            width: 100%;
        }
        
        #preview h3 {
            margin-bottom: 15px;
            color: #63b7a8;
        }
        
        video {
            border-radius: 10px;
            max-width: 90%;
            box-shadow: 0 0 15px rgba(255, 252, 0, 0.3);
        }
        
        #download-btn {
            margin-top: 15px;
            padding: 12px 25px;
            font-size: 1.1rem;
        }
        
        .error {
            color: #ff4d4d;
            font-weight: bold;
        }
        
        .success {
            color: #4ade80;
            font-weight: bold;
        }
        
        .spinner {
            border: 4px solid rgba(255, 252, 0, 0.3);
            border-top: 4px solid #63b7a8;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .info-box {
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            max-width: 600px;
        }
        
        .info-box h3 {
            color: #63b7a8;
            margin-top: 0;
        }
        
        .test-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .status-pass {
            background: #065f46;
            color: #4ade80;
        }
        
        .status-fail {
            background: #7f1d1d;
            color: #ff4d4d;
        }
    </style>
</head>
<body>
    <h1>📥 Snapchat Story Downloader - PHP Test</h1>
    
    <input type="text" id="story_url" placeholder="Paste Snapchat Story URL..." />
    <br />
    <button id="preview-btn">View Story</button>
    <div id="preview"></div>

    <script>
        let currentUrl = "";
        let currentId = "";

        document.getElementById("preview-btn").addEventListener("click", async () => {
            const url = document.getElementById("story_url").value.trim();
            if (!url) {
                alert("Please enter a Snapchat URL");
                return;
            }

            currentUrl = url;

            // Show loading spinner
            document.getElementById("preview").innerHTML = 
                '<div class="spinner"></div><p>Fetching story, please wait...</p>';

            try {
                const formData = new FormData();
                formData.append('action', 'preview');
                formData.append('url', url);

                const res = await fetch(window.location.href, {
                    method: "POST",
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    const videoData = data.data;
                    currentId = videoData.id;

                    const streamUrl = window.location.href + '?action=stream&vid=' + 
                                    videoData.id + '&url=' + encodeURIComponent(currentUrl);

                    let html = '<h3>' + videoData.title + '</h3>';
                    html += '<video id="video" width="480" controls';
                    if (videoData.thumbnail) {
                        html += ' poster="' + videoData.thumbnail + '"';
                    }
                    html += '><source src="' + streamUrl + '" type="video/mp4"></video>';
                    html += '<br><button id="download-btn">⬇ Download as MP4</button>';

                    document.getElementById("preview").innerHTML = html;

                    // Add download functionality
                    document.getElementById("download-btn").onclick = async () => {
                        const formData = new FormData();
                        formData.append('action', 'download');
                        formData.append('url', currentUrl);

                        try {
                            const res = await fetch(window.location.href, {
                                method: "POST",
                                body: formData
                            });

                            const result = await res.json();

                            if (result.success) {
                                // Create download link
                                const a = document.createElement("a");
                                a.href = result.data.download_url;
                                a.download = result.data.filename;
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                
                                document.getElementById("preview").innerHTML += 
                                    '<div class="success">✅ Download completed!</div>';
                            } else {
                                alert("❌ Failed to download video: " + result.error);
                            }
                        } catch (err) {
                            alert("❌ Failed to download video: " + err.message);
                        }
                    };
                } else {
                    document.getElementById("preview").innerHTML = 
                        '<p class="error">❌ ' + data.error + '</p>';
                }
            } catch (err) {
                document.getElementById("preview").innerHTML = 
                    '<p class="error">❌ Something went wrong while fetching the story: ' + err.message + '</p>';
            }
        });
    </script>
</body>
</html>