import React, { useState, useEffect, useRef } from 'react';
import PropTypes from 'prop-types';

const THUMB_BG = '#1a1a2e';

const VideoCard = ({ video, libraryId, cdnHostname, onSelect }) => {
  const [hovered, setHovered] = useState(false);
  const thumbUrl = cdnHostname
    ? `https://${cdnHostname}/${video.guid}/thumbnail.jpg`
    : null;
  const embedUrl = `https://iframe.mediadelivery.net/embed/${libraryId}/${video.guid}`
    + '?autoplay=true&muted=true&controls=false&loop=true&preload=true';

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={() => onSelect({ videoId: video.guid, title: video.title || 'Untitled' })}
      onKeyDown={(e) => e.key === 'Enter' && onSelect({ videoId: video.guid, title: video.title || 'Untitled' })}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        cursor: 'pointer',
        border: '2px solid transparent',
        borderRadius: 4,
        overflow: 'hidden',
        transition: 'border-color .15s, box-shadow .15s',
        boxShadow: hovered ? '0 4px 12px rgba(0,0,0,.2)' : '0 1px 3px rgba(0,0,0,.1)',
        borderColor: hovered ? '#0071c4' : '#ddd',
        background: '#fff',
      }}
    >
      {/* Media area – fixed 16:9 */}
      <div style={{ position: 'relative', aspectRatio: '16/9', background: THUMB_BG, overflow: 'hidden' }}>
        {/* Thumbnail (always in DOM, hidden on hover) */}
        {thumbUrl && (
          <img
            src={thumbUrl}
            alt={video.title}
            style={{
              position: 'absolute', inset: 0,
              width: '100%', height: '100%',
              objectFit: 'cover',
              display: 'block',
              opacity: hovered ? 0 : 1,
              transition: 'opacity .2s',
            }}
            onError={(e) => { e.target.style.display = 'none'; }}
          />
        )}

        {/* Embed iframe – only mounted on hover to avoid loading all upfront */}
        {hovered && (
          <iframe
            src={embedUrl}
            title={video.title}
            allow="autoplay; encrypted-media"
            style={{
              position: 'absolute', inset: 0,
              width: '100%', height: '100%',
              border: 0,
              display: 'block',
            }}
          />
        )}
      </div>

      {/* Caption */}
      <div style={{ padding: '8px 10px' }}>
        <div style={{
          fontSize: '.85rem',
          fontWeight: 600,
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
          color: '#222',
        }}>
          {video.title || 'Untitled'}
        </div>
        <div style={{ fontSize: '.72rem', color: '#999', marginTop: 2 }}>
          {String(video.guid).substring(0, 8)}…
        </div>
      </div>
    </div>
  );
};

VideoCard.propTypes = {
  video: PropTypes.shape({
    guid: PropTypes.string.isRequired,
    title: PropTypes.string,
  }).isRequired,
  libraryId: PropTypes.string.isRequired,
  cdnHostname: PropTypes.string,
  onSelect: PropTypes.func.isRequired,
};

VideoCard.defaultProps = {
  cdnHostname: '',
};

// ─────────────────────────────────────────────────────────────

const BunnyVideoSearch = ({ data, onSelect }) => {
  const { searchEndpoint, libraryId, cdnHostname } = data || {};

  const [videos, setVideos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const debounceRef = useRef(null);

  const fetchVideos = async (q = '') => {
    if (!searchEndpoint) {
      setError('No search endpoint configured');
      setLoading(false);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const url = q ? `${searchEndpoint}?q=${encodeURIComponent(q)}` : searchEndpoint;
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      setVideos(json.items || []);
    } catch (e) {
      setError(e.message || 'Failed to load videos');
    } finally {
      setLoading(false);
    }
  };

  // Auto-load on mount
  useEffect(() => {
    fetchVideos();
    return () => clearTimeout(debounceRef.current);
  }, []);

  const handleQueryChange = (e) => {
    const q = e.target.value;
    setQuery(q);
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => fetchVideos(q), 350);
  };

  return (
    <div style={{ padding: '16px 20px' }}>
      {/* Search input */}
      <div style={{ marginBottom: 16 }}>
        <input
          type="search"
          className="form-control"
          placeholder="Search by title…"
          value={query}
          onChange={handleQueryChange}
          autoFocus
        />
      </div>

      {/* States */}
      {loading && (
        <div style={{ textAlign: 'center', padding: '40px 0', color: '#999' }}>
          Loading videos…
        </div>
      )}

      {!loading && error && (
        <div className="alert alert-danger">{error}</div>
      )}

      {!loading && !error && videos.length === 0 && (
        <div style={{ textAlign: 'center', padding: '40px 0', color: '#999' }}>
          No videos found.
        </div>
      )}

      {/* Grid */}
      {!loading && !error && videos.length > 0 && (
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(3, 1fr)',
          gap: 12,
        }}>
          {videos.map((video) => (
            <VideoCard
              key={video.guid}
              video={video}
              libraryId={libraryId}
              cdnHostname={cdnHostname}
              onSelect={onSelect}
            />
          ))}
        </div>
      )}
    </div>
  );
};

BunnyVideoSearch.propTypes = {
  data: PropTypes.shape({
    searchEndpoint: PropTypes.string,
    libraryId: PropTypes.string,
    cdnHostname: PropTypes.string,
  }),
  onSelect: PropTypes.func,
};

BunnyVideoSearch.defaultProps = {
  data: {},
  onSelect: () => {},
};

export default BunnyVideoSearch;
