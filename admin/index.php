

<html>
    <head><title>Batch upload</title></head>
    <body>
    <p>Please remove all unnecessary tabs from the Excel sheet. The data will automatically be build into a new SQLite database. This might take a while, so please be patient. The old database will be stored with a timestamp for future reference.</p>
    
    
     <form action="upload.php" method="post" enctype="multipart/form-data">
    Select image to upload:
    <input type="file" name="fileToUpload" id="fileToUpload">
    <input type="submit" value="Upload XLS" name="submit">
</form>
    
    
    
</body>
</html>