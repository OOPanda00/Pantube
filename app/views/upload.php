<h2>Upload Video</h2>

<form method="POST" action="upload" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <label>Title</label>
    <input type="text" name="title" required>

    <label>Description</label>
    <textarea name="description"></textarea>

    <label>Video (MP4)</label>
    <input type="file" name="video" accept="video/mp4" required>

    <button type="submit">Upload</button>
</form>

