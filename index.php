<!DOCTYPE html>
<html>
    <head>
        <title>Upload Zip File</title>
    </head>
    <body>
        <h1>Unzip, Validate, Upload!</h1>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="zip_file">
            <input type="submit" name="upload" value="Upload">
        </form>
        </br>

        <?php
        require 'vendor/autoload.php'; // Include the AWS SDK for PHP

        use Aws\S3\S3Client;
        use Aws\S3\Exception\S3Exception;

        // AWS S3 bucket settings


        $bucketName = '';
        $s3Key = '';
        $s3Secret = '';
        $s3Region = '';
        // Function to validate the unzipped content and echo the contents
        function validateUnzippedContent($unzippedPath)
        {
            $csvCount = 0;
            $imageCount = 0;

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($unzippedPath));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    // echo 'Pathname: '; 
                    // echo $file->getPathname() . '<br>';
                    $extension = pathinfo($file->getRealPath(), PATHINFO_EXTENSION);
                    // echo $extension . '<br>';
                    if ($extension === 'csv') {
                        $csvCount++;
                        // echo 'csvCount: ';
                        // echo $csvCount . '<br>';
                    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $imageCount++;
                        // echo 'imageCount: ';
                        // echo $imageCount . '<br>';
                    }
                } 
                // else {
                //     echo 'No file.'; }
            }

            if ($csvCount === 1 && $imageCount >= 1) {
                echo '<h3>Unzipped contents:</h3>';
                echo '<ol>';
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        echo '<li>' . $file->getPathname() . '</li><br>';
                    }
                } echo '</ol>';

                return true;
            } else {
                // echo 'imageCount: ';
                // echo $imageCount . '<br>';
                // echo 'csvCount: ';
                // echo $csvCount . '<br>';
            }

            return false;
        }


        // Function to upload the unzipped folder to AWS S3
        function uploadToS3($unzippedPath)
        {
            global $bucketName, $s3Key, $s3Secret, $s3Region;

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => $s3Region,
                'credentials' => [
                    'key' => $s3Key,
                    'secret' => $s3Secret,
                ],
            ]);

            $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($unzippedPath));

            foreach ($objects as $object) {
                if ($object->isFile()) {
                    $filePath = $object->getPathname();
                    $key = 'sampleFolder/' . str_replace($unzippedPath . '/', '', $filePath);

                    try {
                        $s3->putObject([
                            'Bucket' => $bucketName,
                            'Key' => $key,
                            'SourceFile' => $filePath,
                        ]);
                    } catch (S3Exception $e) {
                        echo 'Error uploading ' . $key . ' to AWS S3: ' . $e->getMessage() . '<br>';
                    }
                }
            }
        }

        if (isset($_POST['upload'])) {
            $zipFile = $_FILES['zip_file']['tmp_name'];

            // Create a temporary directory to extract the zip
            $tempDir = sys_get_temp_dir() . '/' . uniqid('unzipped_', true);
            mkdir($tempDir);

            // Extract the zip file
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === true) {
                $zip->extractTo($tempDir);
                $zip->close();

                // Validate the unzipped content
                if (validateUnzippedContent($tempDir)) {
                    // Upload to AWS S3
                    uploadToS3($tempDir);
                    echo 'Success! The zip file was uploaded to AWS S3.';
                } else {
                    echo 'Invalid unzipped content. It should contain one CSV file and at least one image file.';
                }
            } else {
                echo 'Failed to open the zip file.';
            }

            // Remove the temporary directory
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileInfo) {
                $action = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
                $action($fileInfo->getRealPath());
            }
            rmdir($tempDir);
        }
        ?>
    </body>
</html>
