<div class="bunny-video">
    <% if $VideoID %>
        <iframe 
            src="https://iframe.mediadelivery.net/embed/{$LibraryId}/{$VideoID}?autoplay=<% if $Autoplay %>true<% else %>false<% end_if %>&controls=<% if $Controls %>true<% else %>false<% end_if %>&muted=<% if $Muted %>true<% else %>false<% end_if %>&loop=<% if $Loop %>true<% else %>false<% end_if %>" 
            loading="lazy" 
            style="border:0;position:absolute;top:0;height:100%;width:100%;" 
            allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" 
            allowfullscreen="true">
        </iframe>
    <% else %>
        <p>Kein Video verfügbar</p>
    <% end_if %>
</div>
