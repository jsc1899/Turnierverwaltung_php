<?php
ob_start(); ?>

<div class="row mb-4">
  <div class="col">
    <h1 class="h3"><i class="bi bi-question-circle me-2"></i>Hilfe &amp; Anleitung</h1>
    <p class="text-muted">Anwenderleitfaden für die Turnierverwaltung</p>
  </div>
</div>

<div class="row g-4">
  <!-- Inhaltsverzeichnis -->
  <div class="col-lg-3 d-none d-lg-block">
    <div class="sticky-top" style="top:1rem">
      <div class="card">
        <div class="card-header fw-semibold">Inhalt</div>
        <div class="list-group list-group-flush small">
          <a href="#uebersicht"      class="list-group-item list-group-item-action">Übersicht</a>
          <a href="#rollen"          class="list-group-item list-group-item-action">Rollen &amp; Rechte</a>
          <a href="#turniere"        class="list-group-item list-group-item-action">Turniere</a>
          <a href="#bewerbe"         class="list-group-item list-group-item-action">Bewerbe</a>
          <a href="#spieler"         class="list-group-item list-group-item-action">Spieler &amp; Spielstärke</a>
          <a href="#doppel"          class="list-group-item list-group-item-action">Doppel</a>
          <a href="#auslosung"       class="list-group-item list-group-item-action">Auslosung</a>
          <a href="#gruppenphase"    class="list-group-item list-group-item-action ps-4">↳ Gruppenphase</a>
          <a href="#ko"              class="list-group-item list-group-item-action ps-4">↳ KO-Runde</a>
          <a href="#ko-direkt"       class="list-group-item list-group-item-action ps-4">↳ Nur KO-Runde</a>
          <a href="#doppel-ko"       class="list-group-item list-group-item-action ps-4">↳ Doppel-KO</a>
          <a href="#ergebnisse"      class="list-group-item list-group-item-action">Ergebnisse erfassen</a>
          <a href="#nennungen"       class="list-group-item list-group-item-action">Nennungen (öffentlich)</a>
          <a href="#exporte"         class="list-group-item list-group-item-action">PDF- &amp; CSV-Export</a>
          <a href="#benutzer"        class="list-group-item list-group-item-action">Benutzerverwaltung</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Hauptinhalt -->
  <div class="col-lg-9">

    <!-- Übersicht -->
    <section id="uebersicht" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Übersicht</h2>
      <p>Die Turnierverwaltung ermöglicht die vollständige Organisation von Sportturnieren — von der Spieler-Anmeldung bis zur Ergebniserfassung und dem Ausdruck von Spielplänen.</p>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <div class="card h-100 border-primary">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-trophy me-1 text-primary"></i>Turniere</h6>
              <p class="card-text small text-muted">Verwalte Turniere mit mehreren Bewerben, öffentlicher Ausschreibung und Anmeldeformular.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-success">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-diagram-3 me-1 text-success"></i>Bewerbe</h6>
              <p class="card-text small text-muted">Gruppenphase, KO-Runde, nur KO oder Doppel-KO — je nach Disziplin konfigurierbar.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-warning">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-people me-1 text-warning"></i>Spieler &amp; Doppel</h6>
              <p class="card-text small text-muted">Globales Spielerregister mit Spielstärke, Doppel-Verwaltung, Import-Funktion und öffentlichem Anmeldeformular.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Rollen -->
    <section id="rollen" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Rollen &amp; Rechte</h2>
      <p>Es gibt drei Benutzerrollen:</p>
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Rolle</th>
              <th>Turniere ansehen</th>
              <th>Bearbeiten &amp; Ergebnisse</th>
              <th>Benutzerverwaltung</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="badge bg-danger">Admin</span></td>
              <td><i class="bi bi-check-lg text-success"></i></td>
              <td><i class="bi bi-check-lg text-success"></i></td>
              <td><i class="bi bi-check-lg text-success"></i></td>
            </tr>
            <tr>
              <td><span class="badge bg-primary">Editor</span></td>
              <td><i class="bi bi-check-lg text-success"></i></td>
              <td><i class="bi bi-check-lg text-success"></i></td>
              <td><i class="bi bi-x-lg text-danger"></i></td>
            </tr>
            <tr>
              <td><span class="badge bg-secondary">Viewer</span></td>
              <td><i class="bi bi-check-lg text-success"></i> (nur öffentliche)</td>
              <td><i class="bi bi-x-lg text-danger"></i></td>
              <td><i class="bi bi-x-lg text-danger"></i></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Nicht angemeldete Besucher können öffentliche Turniere und Spielpläne lesen, aber nichts bearbeiten. Die Admin-Rolle wird der in der Konfiguration hinterlegten E-Mail-Adresse automatisch zugewiesen.
      </div>
    </section>

    <!-- Turniere -->
    <section id="turniere" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Turniere</h2>
      <p>Ein Turnier ist der Rahmen für einen Veranstaltungstag. Es kann mehrere <strong>Bewerbe</strong> (Disziplinen) enthalten.</p>

      <h5 class="mt-3">Turnier anlegen</h5>
      <p>Klicke auf der Startseite auf <strong>Neues Turnier</strong>. Pflichtfeld ist der Name. Optional:</p>
      <ul>
        <li><strong>Datum</strong> — Erscheint auf der Übersicht und in Exporten</li>
        <li><strong>Veranstalter / Sportart</strong> — Für den Aushang-PDF</li>
        <li><strong>Ausschreibungs-URL</strong> — Link zu einem externen Dokument</li>
        <li><strong>Max. Bewerbe pro Nennung</strong> — Wie viele Bewerbe ein Spieler gleichzeitig wählen darf</li>
        <li><strong>Öffentlich sichtbar</strong> — Nicht-angemeldete Besucher sehen das Turnier nur, wenn diese Option aktiv ist</li>
        <li><strong>Spielstärke anzeigen</strong> — Zeigt die Spielstärke im öffentlichen Anmeldeformular</li>
        <li><strong>Turnierstatus</strong> — Offen (Anmeldungen möglich), Anmeldeschluss (kein Formular sichtbar) oder Abgeschlossen</li>
        <li><strong>Turnierbild / Ausschreibung</strong> — PDF oder Bild als Upload</li>
      </ul>

      <h5 class="mt-3">Turnierseite</h5>
      <p>Die Turnierseite zeigt alle Bewerbe und — für Admins/Editoren — die eingegangenen Nennungen. Von hier aus können:</p>
      <ul>
        <li>Nennungen bestätigt oder abgelehnt werden (einzeln oder per Sammelbestätigung)</li>
        <li>Änderungsanträge von Spielern bearbeitet werden</li>
        <li>PDF-Exporte (Aushang, Nennungsliste) aufgerufen werden</li>
        <li>Neue Bewerbe angelegt werden</li>
      </ul>
    </section>

    <!-- Bewerbe -->
    <section id="bewerbe" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Bewerbe</h2>
      <p>Jeder Bewerb durchläuft folgende Phasen: <strong>Setup → Gruppenphase → KO-Runde → Abgeschlossen</strong> (je nach Modus können Phasen entfallen).</p>

      <h5 class="mt-3">Bewerbseinstellungen</h5>
      <p>Über den Button <strong>Einstellungen</strong> auf der Bewerbsseite kann konfiguriert werden:</p>
      <ul>
        <li><strong>Name</strong> — Bezeichnung des Bewerbs (z.B. „Herren Einzel")</li>
        <li><strong>Modus</strong> — Gruppenphase + KO, Nur KO-Runde oder Doppel-KO</li>
        <li><strong>Gruppengröße</strong> — Anzahl Spieler pro Gruppe (Gruppenphase)</li>
        <li><strong>KO-Aufstieg</strong> — Wie viele Spieler pro Gruppe in die KO-Runde aufsteigen (0 = nur Gruppenphase)</li>
        <li><strong>Platz-3-Spiel</strong> — Ob die Halbfinalverlierer um Platz 3 spielen</li>
        <li><strong>Max. Teilnehmer</strong> — Obergrenze für Teilnehmer (0 = unbegrenzt); bei Doppelbewerben zählen Doppel, bei Einzelbewerben Spieler</li>
        <li><strong>Anmeldungen offen</strong> — Ob der Bewerb im Anmeldeformular wählbar ist</li>
        <li><strong>Setzung anzeigen</strong> — Ob Setzungsnummern im KO-Raster sichtbar sind (nur KO-Modi)</li>
        <li><strong>Setzungsreihenfolge</strong> — Höhere Spielstärke = stärker (Standard) oder niedrigere = stärker (Tennis)</li>
      </ul>

      <h5 class="mt-3">Doppelbewerb</h5>
      <p>Wird ein Bewerb als <strong>Doppelbewerb</strong> markiert, gelten folgende Besonderheiten:</p>
      <ul>
        <li>Dem Bewerb werden <strong>Doppel</strong> (Paare aus zwei Spielern) statt Einzelspieler zugewiesen</li>
        <li><strong>Max. Teilnehmer</strong> zählt Doppel (nicht Einzelspieler)</li>
        <li>Im öffentlichen Anmeldeformular erscheint ein Badge <em>Doppelbewerb</em> und ein optionales Feld für den gewünschten Partner</li>
        <li>Die Bewerbskachel auf der Turnierseite zeigt die Anzahl zugewiesener Doppel</li>
      </ul>

      <h5 class="mt-3">Spieler/Doppel einem Bewerb zuweisen</h5>
      <p>Im Setup können Einzelspieler aus dem globalen Register über die Suchleiste dem Bewerb hinzugefügt werden. Die Spielstärke wird aus dem Register übernommen und kann für diesen Bewerb individuell angepasst werden.</p>
      <p>Bei Doppelbewerben werden stattdessen Doppel aus dem Doppelregister zugewiesen. Jeder Spieler darf pro Bewerb nur in einem Doppel sein.</p>
    </section>

    <!-- Spieler -->
    <section id="spieler" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Spieler &amp; Spielstärke</h2>
      <p>Das <strong>Spielerregister</strong> (Menüpunkt oben) enthält alle Spieler übergreifend über alle Turniere.</p>

      <h5 class="mt-3">Spieler anlegen und bearbeiten</h5>
      <ul>
        <li>Name, Vorname, Verein, Geschlecht, Lizenznummer und E-Mail</li>
        <li><strong>Spielstärke</strong> — Globaler Standardwert; kann pro Bewerb überschrieben werden</li>
      </ul>

      <h5 class="mt-3">Import per CSV</h5>
      <p>Über <strong>Spieler importieren</strong> können Spieler per CSV-Datei massenweise angelegt werden. Die Vorlage ist über den gleichnamigen Button herunterladbar. Spalten: Name, Vorname, Verein, Geschlecht, Lizenznummer, E-Mail, Spielstärke.</p>

      <h5 class="mt-3">Spielstärke und Setzung</h5>
      <p>Bei der Auslosung werden Spieler nach Spielstärke gereiht:</p>
      <ul>
        <li>S1 = stärkster Spieler → obere Hälfte des Brackets</li>
        <li>S2 = zweitstärkster → untere Hälfte</li>
        <li>S3/S4 zufällig in je einer Hälftenmitte</li>
        <li>S5–S8 zufällig in den Viertelpositionen usw.</li>
        <li>Spieler ohne Spielstärke (0) gelten als schwächste Spieler</li>
        <li>Im Tennis-Modus (niedrigere Stärke = besser) wird die Reihenfolge umgekehrt</li>
      </ul>
    </section>

    <!-- Doppel -->
    <section id="doppel" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Doppel</h2>
      <p>Doppel (Spielerpaare) werden im <strong>Spielerregister</strong> unter dem Tab <em>Doppel</em> zentral verwaltet und können danach Doppelbewerben zugewiesen werden.</p>

      <h5 class="mt-3">Doppel anlegen</h5>
      <ol>
        <li>Im Spielerregister auf den Tab <strong>Doppel</strong> wechseln</li>
        <li>Über <strong>Neues Doppel</strong> zwei Spieler aus dem Register auswählen</li>
        <li>Der Name wird automatisch aus den Nachnamen gebildet (z.B. „Müller / Huber"), kann aber angepasst werden</li>
        <li>Die <strong>Spielstärke</strong> ergibt sich automatisch als Summe der Einzelspielstärken je Sportart; beim Speichern wird sie berechnet</li>
      </ol>

      <h5 class="mt-3">Spielstärke bei Doppeln</h5>
      <ul>
        <li>Basis ist die sportartspezifische Spielstärke beider Spieler aus <em>player_skill</em></li>
        <li>Fehlt ein Wert bei Tennis, wird automatisch <strong>10,0</strong> als Standardwert angenommen</li>
        <li>Die Doppelstärke wird bei jeder Änderung der Einzelspielstärken automatisch aktualisiert</li>
      </ul>

      <h5 class="mt-3">Doppel einem Bewerb zuweisen</h5>
      <p>Auf der Bewerbsseite eines Doppelbewerbs kann über die Suchleiste ein Doppel aus dem Register hinzugefügt werden. Für jedes Doppel kann die Spielstärke bewerbs-spezifisch angepasst werden.</p>
      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Jeder Einzelspieler darf pro Bewerb nur in <strong>einem</strong> Doppel teilnehmen. Beim Hinzufügen wird dies automatisch geprüft.
      </div>

      <h5 class="mt-3">Doppel aus Nennungen bilden</h5>
      <p>Melden sich Spieler für einen Doppelbewerb über das öffentliche Anmeldeformular an, erscheinen sie nach Admin-Bestätigung im Abschnitt <em>Bestätigte Nennungen (noch ohne Partner)</em> auf der Bewerbsseite. Der Admin kann dort zwei Spieler auswählen und mit <strong>Doppel bilden</strong> direkt ein Doppel anlegen und dem Bewerb zuweisen.</p>
    </section>

    <!-- Auslosung -->
    <section id="auslosung" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Auslosung</h2>

      <h5 id="gruppenphase" class="mt-3">Gruppenphase</h5>
      <p>Voraussetzung: Modus <em>Gruppenphase + KO</em>, mindestens so viele Spieler wie eine Gruppenanzahl × Gruppengröße.</p>
      <ol>
        <li>Klicke auf <strong>Gruppen auslosen</strong> — Spieler werden gleichmäßig auf die Gruppen verteilt</li>
        <li>Die Gruppenreihenfolge kann per Drag &amp; Drop angepasst werden (<strong>Gruppen neu ordnen</strong>)</li>
        <li>Alle Gruppenspiele werden automatisch als Round-Robin-Spielplan erzeugt</li>
        <li>Ergebnisse erfassen → Tabelle wird automatisch berechnet</li>
        <li>Wenn alle Gruppenspiele eingetragen sind, erscheint <strong>KO-Runde auslosen</strong></li>
      </ol>
      <p>Die Gruppenplatzierung (Wertung: Sieg=2 Pkt., Unentschieden=1 Pkt., Niederlage=0 Pkt.) bestimmt, welche Spieler in die KO-Runde aufsteigen. Tiebreaker: Tordifferenz → erzielte Tore.</p>

      <h5 id="ko" class="mt-3">KO-Runde (nach Gruppenphase)</h5>
      <p>Nach Abschluss der Gruppenphase werden die qualifizierten Spieler (Erst- und Zweitplatzierte jeder Gruppe, je nach Einstellung) in ein KO-Bracket ausgelost. Gesetzt werden sie nach Gruppenplatzierung — Gruppensieger gegen Gruppenzweite aus anderen Gruppen, um mögliche Wiederholungsspiele zu vermeiden.</p>

      <h5 id="ko-direkt" class="mt-3">Nur KO-Runde</h5>
      <p>Im Modus <em>Nur KO-Runde</em> entfällt die Gruppenphase. Alle zugewiesenen Spieler kommen direkt in ein KO-Bracket:</p>
      <ol>
        <li>Spieler dem Bewerb zuweisen und Spielstärken kontrollieren</li>
        <li><strong>KO-Bracket auslosen</strong> — erzeugt ein gesetztes Bracket; Freilose werden automatisch weitergerückt</li>
        <li>Ergebnisse erfassen</li>
      </ol>

      <h5 id="doppel-ko" class="mt-3">Doppel-KO</h5>
      <p>Im Doppel-KO scheidet ein Spieler erst nach zwei Niederlagen aus. Das Bracket besteht aus:</p>
      <ul>
        <li><strong>Winners Bracket (WB)</strong> — Spieler ohne Niederlage</li>
        <li><strong>Losers Bracket (LB)</strong> — Spieler mit genau einer Niederlage; LB-Runden wechseln zwischen Minor (neue WB-Verlierer kommen dazu) und Major (reine LB-Spiele)</li>
        <li><strong>Grand Final</strong> — WB-Sieger vs. LB-Sieger</li>
      </ul>
      <p>Verbindungslinien im LB-Raster zeigen, welche Spiele logisch zusammengehören. Setzungen werden nur im WB angezeigt.</p>
      <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Nach jeder Ergebnisänderung werden alle abhängigen Spieler automatisch neu berechnet. Es ist nicht nötig, das Bracket manuell zu aktualisieren.
      </div>
    </section>

    <!-- Ergebnisse -->
    <section id="ergebnisse" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Ergebnisse erfassen</h2>
      <p>Ergebnisse werden direkt auf der Bewerbsseite eingetragen:</p>
      <ul>
        <li><strong>Gruppenspiele</strong> — Score-Felder neben jedem Spiel; mit <kbd>Enter</kbd> oder Klick auf <strong>Speichern</strong> bestätigen. Mehrere Ergebnisse können per <strong>Alle speichern</strong> gleichzeitig übermittelt werden.</li>
        <li><strong>KO-Spiele</strong> — Score-Felder im KO-Raster; nach dem Speichern rückt der Gewinner automatisch in die nächste Runde vor.</li>
        <li><strong>Ergebnis korrigieren</strong> — Mit dem ✕-Button neben einem eingetragenen Ergebnis kann es gelöscht werden; alle abhängigen Runden werden automatisch zurückgesetzt.</li>
      </ul>
      <p>Ein Unentschieden ist möglich (beide Scores gleich). Bei KO-Spielen muss es einen Gewinner geben — gleiche Scores bleiben gespeichert, aber der Fortschritt ins nächste Spiel erfolgt erst, wenn ein echter Gewinner feststeht.</p>

      <h5 class="mt-3">Spielkarten drucken</h5>
      <p>Über <strong>Spielkarten PDF</strong> können Papier-Ergebniszettel für alle Spiele gedruckt werden.</p>
    </section>

    <!-- Nennungen -->
    <section id="nennungen" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Nennungen (öffentliches Anmeldeformular)</h2>
      <p>Spieler können sich über die öffentliche Turnierseite selbst anmelden, ohne einen Account zu benötigen.</p>

      <h5 class="mt-3">Ablauf für Spieler</h5>
      <ol>
        <li>Turnierseite aufrufen → <strong>Anmelden</strong></li>
        <li>Name, Kontaktdaten und gewünschte Bewerbe auswählen → Absenden</li>
        <li>Bei Doppelbewerben kann optional ein <strong>Gewünschter Partner</strong> angegeben werden (rein informativ für den Admin)</li>
        <li>Die Nennung erscheint als <em>ausstehend</em> beim Admin — der Spieler erhält keine automatische Bestätigung</li>
        <li>Sobald der Admin alle Bewerbe der Nennung bestätigt hat, wird automatisch ein <strong>Magic-Link</strong> per E-Mail verschickt (7 Tage gültig)</li>
        <li>Über diesen Link kann die Nennung zurückgezogen oder ein Änderungsantrag gestellt werden</li>
      </ol>

      <h5 class="mt-3">Ablauf für Admins (Einzelbewerbe)</h5>
      <ul>
        <li>Neue Nennungen erscheinen auf der Turnierseite unter <em>Ausstehende Nennungen</em></li>
        <li>Bestätigung: Einzeln pro Bewerb oder alle auf einmal mit <strong>Alle bestätigen</strong> — der Spieler wird automatisch dem Bewerb zugeordnet</li>
        <li>Der Magic-Link wird automatisch versendet, sobald alle Bewerbe einer Nennung entschieden sind</li>
        <li>Wird eine Nennung vollständig abgelehnt, wird kein Magic-Link gesendet</li>
        <li>Änderungsanträge erscheinen unter <em>Änderungsanträge</em>; nach Bestätigung oder Ablehnung wird der Spieler per E-Mail informiert</li>
      </ul>

      <h5 class="mt-3">Ablauf für Admins (Doppelbewerbe)</h5>
      <p>Bei Doppelbewerben melden sich Spieler als Einzelpersonen an — der Admin weist Doppelpartner zu:</p>
      <ol>
        <li>Nennung auf der Turnierseite bestätigen → Spieler erscheint im Abschnitt <em>Bestätigte Nennungen (noch ohne Partner)</em> auf der Bewerbsseite</li>
        <li>Sobald mindestens zwei Spieler ohne Partner vorhanden sind, können zwei davon über das <strong>Doppel bilden</strong>-Formular gepaart werden</li>
        <li>Das Doppel wird automatisch angelegt (oder ein bestehendes wiederverwendet) und dem Bewerb zugewiesen</li>
        <li>Wunschpartner-Angaben der Spieler sind in der Nennungsübersicht und im Paarungsbereich sichtbar</li>
      </ol>
      <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Zieht ein Spieler aus einem Doppelbewerb zurück, wird das gesamte Doppel aus dem Bewerb entfernt. Die Nennung des Partners wird auf <em>ausstehend</em> zurückgesetzt, sodass der Admin ihn neu zuordnen oder ablehnen kann.
      </div>

      <h5 class="mt-3">Nennungsänderung über Magic-Link</h5>
      <p>Über den Magic-Link können Spieler:</p>
      <ul>
        <li>Die gesamte Nennung <strong>zurückziehen</strong> (erzeugt einen Rückzugsantrag)</li>
        <li><strong>Bewerbe ändern</strong> — Bewerbe hinzufügen oder abmelden; bei Doppelbewerben kann der Wunschpartner angegeben oder aktualisiert werden</li>
        <li>Den <strong>Gewünschten Partner</strong> für einen bereits zugeteilten Doppelbewerb jederzeit aktualisieren (ohne Änderungsantrag)</li>
      </ul>
      <p>Alle Änderungen (außer dem Partner-Namen) müssen vom Admin bestätigt werden. Nach der Bearbeitung erhält der Spieler eine E-Mail mit dem Ergebnis.</p>

      <h5 class="mt-3">Link nachträglich anfordern</h5>
      <p>Ist der Magic-Link abgelaufen oder nie angekommen, können Spieler unter <strong>Nennung verwalten</strong> (Menü oben) mit ihrer E-Mail-Adresse einen neuen Link anfordern. Der Link wird nur gesendet, wenn unter dieser E-Mail-Adresse eine Nennung für ein laufendes Turnier existiert.</p>

      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Ohne konfigurierte E-Mail-Einstellungen (<code>MAIL_HOST</code>) werden Magic-Links und Benachrichtigungen statt per E-Mail direkt im Admin-Interface als Flash-Nachricht angezeigt.
      </div>
    </section>

    <!-- Exporte -->
    <section id="exporte" class="mb-5">
      <h2 class="h4 border-bottom pb-2">PDF- &amp; CSV-Export</h2>
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>Export</th><th>Aufruf über</th><th>Inhalt</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Aushang</td>
              <td>Turnierseite</td>
              <td>Turnier-Übersicht mit QR-Code zum Anmeldeformular</td>
            </tr>
            <tr>
              <td>Nennungsliste PDF/CSV</td>
              <td>Turnierseite</td>
              <td>Alle Nennungen inkl. Änderungsanträge</td>
            </tr>
            <tr>
              <td>Spielerliste PDF/CSV</td>
              <td>Turnierseite</td>
              <td>Alle am Turnier teilnehmenden Spieler</td>
            </tr>
            <tr>
              <td>Gruppenplan PDF</td>
              <td>Bewerbsseite</td>
              <td>Alle Gruppen mit Spielplan und Tabelle</td>
            </tr>
            <tr>
              <td>KO-Bracket PDF</td>
              <td>Bewerbsseite</td>
              <td>KO-Raster (Querformat)</td>
            </tr>
            <tr>
              <td>Spielkarten PDF</td>
              <td>Bewerbsseite</td>
              <td>Papier-Ergebniszettel für jeden Spieler</td>
            </tr>
            <tr>
              <td>Bewerbsspieler PDF/CSV</td>
              <td>Bewerbsseite</td>
              <td>Spielerliste des Bewerbs mit Spielstärke</td>
            </tr>
            <tr>
              <td>Globales Spielerregister PDF/CSV</td>
              <td>Spielerregister</td>
              <td>Alle Spieler der Datenbank</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Benutzerverwaltung -->
    <section id="benutzer" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Benutzerverwaltung</h2>
      <p>Nur Admins können unter <strong>Benutzer</strong> (Menü oben) andere Benutzer einsehen und deren Rollen ändern.</p>
      <ul>
        <li>Neue Benutzer registrieren sich über <strong>Registrieren</strong> — sie erhalten zunächst die Rolle <em>Viewer</em></li>
        <li>Der Admin kann die Rolle auf <em>Editor</em> oder <em>Admin</em> hochstufen</li>
        <li>Benutzer können auch gelöscht werden (außer dem eigenen Account)</li>
        <li>Die in der Konfiguration hinterlegte <code>ADMIN_EMAIL</code> behält automatisch immer die Admin-Rolle</li>
      </ul>
      <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Neue Benutzerkonten müssen zunächst die E-Mail-Adresse bestätigen, bevor sie sich anmelden können.
      </div>
    </section>

  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/_base.php';
