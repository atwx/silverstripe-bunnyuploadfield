<div class="bunny-video-upload-field" 
     data-field-name="$Name" 
     data-video-id="$Value"
     data-endpoint="$Endpoint"
     data-library-id="$LibraryId">
    
    <div class="upload-container">
        <input type="file" 
               id="{$ID}_file" 
               accept="video/*" 
               class="bunny-file-input" />
        
        <label for="{$ID}_file" class="upload-button">
            <span class="icon">📹</span>
            <span class="text">Video auswählen</span>
        </label>
        
        <div class="upload-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-text">0%</div>
        </div>
        
        <div class="upload-status"></div>
        
        <% if $Value %>
        <div class="current-video">
            <div class="video-info">
                <strong>Video ID:</strong> $Value
            </div>
            <div class="video-preview">
                <iframe src="https://iframe.mediadelivery.net/$LibraryId/$Value"
                        loading="lazy"
                        style="border: 0; width: 100%; max-width: 640px; aspect-ratio: 16/9;"
                        allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                        allowfullscreen="true"></iframe>
            </div>
            <button type="button" class="remove-video btn btn-danger">
                Video entfernen
            </button>
        </div>
        <% end_if %>
    </div>
    
    <input type="hidden" 
           name="$Name" 
           id="$ID" 
           value="$Value" 
           class="bunny-video-id-input" />
</div>
