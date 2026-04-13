### [1.2.2]

- Ein Ein/Aus-Schalter für den Media Fallback in der Administration wurde hinzugefügt und der Aktivierungszustand wird getrennt von der Host-URL gespeichert.
- Die Host-Whitelist des Media Fallbacks wurde entfernt, sodass der Fallback in `APP_ENV=dev` mit jeder Development-URL genutzt werden kann.
- Statusanzeige und Dokumentation in der Administration wurden auf das neue Media-Fallback-Verhalten angepasst.

### [1.2.1]

- Das Logo der Storefront-Toolbar wurde auf den öffentlichen Bundle-Asset-Pfad umgestellt.
- Für Toolbar-Labels, Bestätigungsdialog und Maintenance-Fehlermeldungen wurden Storefront-Snippets hinzugefügt.
- Fest verdrahtete englische Toolbar-Texte wurden entfernt, sodass sich die Toolbar an der aktiven Storefront-Sprache orientiert.

### [1.2.0]

- Eine nur in dev aktive, schwebende Storefront-Toolbar mit schnellen Maintenance-Aktionen und Shortcuts wurde hinzugefügt.
- Ein Storefront-Bestätigungsdialog und automatisches Seiten-Refresh nach erfolgreichen Maintenance-Aktionen wurden hinzugefügt.
- Toolbar-Styling und Sichtbarkeit der Icons wurden verbessert.

### [1.1.0]

- Der Maintenance-Tab wurde mit eigenen Aktionen für Cache leeren, Themes kompilieren und OPcache zurücksetzen hinzugefügt.
- Oberhalb der Maintenance-Aktionen wurde ein Shop-Info-Bereich (Umgebung, HTTP-Cache-Status, Cache-Adapter) hinzugefügt.
- Das Layout der Maintenance-Aktionen wurde von 3 Spalten auf 3 untereinander angeordnete Zeilen umgestellt.
- Neben jedem Maintenance-Button werden die zugehörigen Shortcuts im Code-Stil angezeigt.
- Ein Ja/Nein-Bestätigungsdialog wurde vor der Ausführung von Maintenance-Shortcuts hinzugefügt.
- Der Cache-leeren-Button in der Plugin-Seite nutzt jetzt den Standard-Cache-Clear-Ablauf von Shopware.
- Die doppelte Nutzung eines eigenen Cache-Clear-Aufrufs im Admin-API-Service des Plugins wurde entfernt.

### [1.0.0]

- Plugin erstellt
