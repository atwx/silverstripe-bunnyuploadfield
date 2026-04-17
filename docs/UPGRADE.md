# Upgrade-Anleitung

## Upgrade auf Version 2.0

### Wichtige Änderungen

**DBBunnyVideo speichert jetzt vollständiges JSON**

Das `DBBunnyVideo`-Feld speichert jetzt das komplette Video-JSON von Bunny CDN als Text-Feld, nicht nur die Video-ID. Dies ermöglicht:

- Speicherung aller Video-Metadaten von Bunny
- Playback-Einstellungen (Autoplay, Controls, Muted, Loop) pro Video
- Zugriff auf alle Bunny-Video-Eigenschaften (Titel, Dauer, etc.)
- Bessere Offline-Funktionalität ohne zusätzliche API-Calls

### Datenbank-Schema

Statt `DBVarchar` mit nur der Video-ID verwendet das Feld jetzt `DBText` und speichert JSON:

```json
{
  "guid": "video-guid",
  "title": "Video Title",
  "autoplay": false,
  "controls": true,
  "muted": false,
  "loop": false
}
```

### Migration

Nach dem Update müssen Sie die Datenbank aktualisieren:

```bash
php vendor/silverstripe/framework/cli-script.php dev/build flush=1
```

Bei DDEV:
```bash
ddev exec php vendor/silverstripe/framework/cli-script.php dev/build flush=1
```

**Bestehende Daten:** Wenn Sie bereits Video-IDs gespeichert haben, werden diese automatisch in JSON konvertiert. Die Video-ID wird als `guid`-Feld im JSON gespeichert.

### Was ändert sich für Sie?

**Im CMS:**
- Wenn Sie ein Video auswählen, können Sie jetzt direkt die Playback-Einstellungen über Checkboxen festlegen
- Diese Einstellungen werden im JSON gespeichert und im Frontend verwendet
- Die Video-Vorschau im Backend hat kein Autoplay mehr

**Im Code - neuer Ansatz:**
```php
// Nur Video-ID setzen (wird automatisch als JSON gespeichert):
$page->BunnyVideoID = 'video-guid';

// Mit Einstellungen:
$page->BunnyVideoID = json_encode([
    'guid' => 'video-guid',
    'title' => 'Mein Video',
    'autoplay' => true,
    'controls' => true,
    'muted' => false,
    'loop' => false,
]);

// Oder als Array (wird automatisch zu JSON):
$page->BunnyVideoID = [
    'guid' => 'video-guid',
    'autoplay' => true,
];
```

**Zugriff auf Daten:**
```php
// Video-ID abrufen
$videoId = $page->BunnyVideoID->getVideoID();

// Titel abrufen
$title = $page->BunnyVideoID->getTitle();

// Einstellungen abrufen
$autoplay = $page->BunnyVideoID->getAutoplay();
$controls = $page->BunnyVideoID->getControls();
```

**In Templates:**
```html
<!-- Verwendet die gespeicherten Einstellungen: -->
$BunnyVideoID.EmbedHTML

<!-- Parameter überschreiben: -->
$BunnyVideoID.EmbedHTML(800, 450, true, true, false, false)
<!-- (width, height, autoplay, controls, muted, loop) -->

<!-- Video-Details anzeigen: -->
<h2>$BunnyVideoID.Title</h2>
<p>Video ID: $BunnyVideoID.VideoID</p>
```

### Rückwärtskompatibilität

Die Änderungen sind weitgehend rückwärtskompatibel:
- Bestehende Templates funktionieren weiterhin
- Das Setzen einer einfachen String-Video-ID funktioniert weiterhin
- `getVideoID()` gibt weiterhin die Video-ID zurück

### Bei Problemen

Falls nach dem Update Probleme auftreten:

1. Stellen Sie sicher, dass `dev/build` erfolgreich durchgelaufen ist
2. Leeren Sie den Cache: `?flush=1` im Browser
3. Prüfen Sie die Logs in `silverstripe.log`
4. Stellen Sie sicher, dass das Feld als `DBBunnyVideo` definiert ist (nicht als `Varchar` oder `Text`)

Bei Fragen oder Problemen erstellen Sie bitte ein Issue auf GitHub.
