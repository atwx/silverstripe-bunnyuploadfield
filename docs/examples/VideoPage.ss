<%-- Example template for VideoPage.ss --%>

<div class="video-page">
    <h1>$Title</h1>
    
    <% if $BunnyVideoID %>
        <div class="video-container">
            <% if $VideoTitle %>
                <h2>$VideoTitle</h2>
            <% end_if %>
            
            <%-- Basic embed --%>
            $BunnyVideoID.EmbedHTML
            
            <%-- Or with custom settings --%>
            <%-- $BunnyVideoID.getEmbedHTML(800, 450, false, true, false, false) --%>
            
            <% if $VideoDescription %>
                <div class="video-description">
                    $VideoDescription
                </div>
            <% end_if %>
        </div>
    <% else %>
        <p>Kein Video verfügbar.</p>
    <% end_if %>
    
    <div class="content">
        $Content
    </div>
</div>

<style>
.video-container {
    margin: 2rem 0;
}

.video-container h2 {
    margin-bottom: 1rem;
}

.video-description {
    margin-top: 1rem;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 4px;
}
</style>
