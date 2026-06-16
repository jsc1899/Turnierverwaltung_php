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
          <a href="#teams"           class="list-group-item list-group-item-action">Teams &amp; Teambewerb</a>
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
              <p class="card-text small text-muted">Verwalte Turniere mit mehreren Bewerben, öffentlicher Ausschreibung und Anmeldeformular. Reihenfolge per Drag &amp; Drop änderbar.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-success">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-diagram-3 me-1 text-success"></i>Bewerbe</h6>
              <p class="card-text small text-muted">Gruppenphase, KO-Runde, nur KO oder Doppel-KO — als Einzel-, Doppel- oder Teambewerb konfigurierbar.</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-warning">
            <div class="card-body">
              <h6 class="card-title"><i class="bi bi-people me-1 text-warning"></i>Spieler, Doppel &amp; Teams</h6>
              <p class="card-text small text-muted">Globales Spielerregister mit Spielstärke, Doppel- und Team-Verwaltung, Import-Funktion und öffentlichem Anmeldeformular.</p>
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
        Nicht angemeldete Besucher können öffentliche Turniere und Spielpläne lesen (inkl. Einzel-Ergebnisse bei Teambewerben), aber nichts bearbeiten. Die Admin-Rolle wird der in der Konfiguration hinterlegten E-Mail-Adresse automatisch zugewiesen.
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

      <h5 class="mt-3">Reihenfolge ändern</h5>
      <p>Admins und Editoren können Turnier-Kacheln per <strong>Drag &amp; Drop</strong> umsortieren. Das Griffsymbol (<i class="bi bi-grip-horizontal"></i>) am oberen Rand jeder Kachel als Anfasspunkt verwenden. Die Reihenfolge wird automatisch gespeichert. Wenn ein Status- oder Sportart-Filter aktiv ist, ist Drag &amp; Drop vorübergehend deaktiviert (um inkonsistente Reihenfolgen zu vermeiden).</p>

      <h5 class="mt-3">Turnierseite</h5>
      <p>Die Turnierseite zeigt alle Bewerbe und — für Admins/Editoren — die eingegangenen Nennungen. Auch die Reihenfolge der Bewerb-Kacheln ist per Drag &amp; Drop änderbar. Von hier aus können:</p>
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
        <li><strong>Bewerbstyp</strong> — Einzelbewerb, Doppelbewerb oder Teambewerb (kann nach dem ersten Auslosen nicht mehr geändert werden)</li>
        <li><strong>Spiele pro Team</strong> — Nur bei Teambewerb; Anzahl der Einzel-Duelle pro Mannschaftsspiel (0 = direkte Gesamtergebnis-Eingabe, 1 = ein Einzel ohne Spielerauswahl; ab 2 wird pro Duel eine Spielerauswahl eingeblendet; nach dem ersten Auslosen gesperrt)</li>
        <li><strong>Modus</strong> — Gruppenphase + KO, Nur KO-Runde oder Doppel-KO (nach dem ersten Auslosen gesperrt)</li>
        <li><strong>Gruppengröße</strong> — Anzahl Spieler pro Gruppe (Gruppenphase), 3–10 Spieler</li>
        <li><strong>KO-Aufstieg</strong> — Wie viele Spieler pro Gruppe in die KO-Runde aufsteigen (0 = nur Gruppenphase)</li>
        <li><strong>Platz-3-Spiel</strong> — Ob die Halbfinalverlierer um Platz 3 spielen</li>
        <li><strong>Max. Teilnehmer</strong> — Obergrenze (0 = unbegrenzt)</li>
        <li><strong>Anmeldungen offen</strong> — Ob der Bewerb im Anmeldeformular wählbar ist</li>
        <li><strong>Setzung anzeigen / Setzungsreihenfolge</strong> — Für KO-Modi; höhere oder niedrigere Spielstärke gilt als besser</li>
      </ul>

      <h5 class="mt-3">Doppelbewerb</h5>
      <p>Wird ein Bewerb als <strong>Doppelbewerb</strong> markiert, gelten folgende Besonderheiten:</p>
      <ul>
        <li>Dem Bewerb werden <strong>Doppel</strong> (Paare aus zwei Spielern) statt Einzelspieler zugewiesen</li>
        <li><strong>Max. Teilnehmer</strong> zählt Doppel (nicht Einzelspieler)</li>
        <li>Im öffentlichen Anmeldeformular erscheint ein Badge <em>Doppelbewerb</em> und ein optionales Feld für den gewünschten Partner</li>
      </ul>

      <h5 class="mt-3">Spieler/Doppel einem Bewerb zuweisen</h5>
      <p>Im Setup können Spieler oder Doppel aus dem globalen Register über die Suchleiste dem Bewerb hinzugefügt werden. Die Spielstärke wird aus dem Register übernommen und kann für diesen Bewerb individuell angepasst werden.</p>
    </section>

    <!-- Spieler -->
    <section id="spieler" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Spieler &amp; Spielstärke</h2>
      <p>Das <strong>Spielerregister</strong> (Menüpunkt oben) enthält alle Spieler übergreifend über alle Turniere.</p>

      <h5 class="mt-3">Spieler anlegen und bearbeiten</h5>
      <ul>
        <li><strong>Pflichtfeld:</strong> Name</li>
        <li><strong>Optional:</strong> Vorname, Verein, Geschlecht, Pass-Nr., E-Mail</li>
        <li><strong>Spielstärke</strong> — Globaler Standardwert; kann pro Bewerb überschrieben werden</li>
      </ul>

      <h5 class="mt-3">Spielerprofil</h5>
      <p>Ein Klick auf einen Spieler öffnet das Profil-Modal mit zwei Tabs:</p>
      <ul>
        <li><strong>Stammdaten</strong> — Bearbeiten von Name, Verein, Spielstärken (nur Editoren/Admins)</li>
        <li><strong>Bewerbe</strong> — Übersicht aller Bewerbe als Einzelspieler, Doppel-Zuordnungen und Teams, jeweils mit Turniername, Bewerbsname und aktuellem Status</li>
      </ul>

      <h5 class="mt-3">Sportart-Spielstärken</h5>
      <p>Pro Spieler können mehrere sportartspezifische Spielstärken hinterlegt werden (Tischtennis, Tennis, Fußball, Cornhole). Die Übersichtstabelle zeigt für jede Sportart eine eigene Spalte mit dem Symbol der Sportart in der Titelzeile.</p>

      <h5 class="mt-3">RatingsCentral-Abgleich (Tischtennis)</h5>
      <p>Spieler können mit ihrem Profil auf <strong>RatingsCentral</strong> verknüpft werden, um die Tischtennis-Spielstärke automatisch zu aktualisieren:</p>
      <ol>
        <li>Im Spielerprofil-Modal die <strong>RatingsCentral-ID</strong> eintragen (die Zahl aus der Profil-URL, z.B. <code>5728</code>) und speichern</li>
        <li>Über den <i class="bi bi-box-arrow-up-right"></i>-Button das externe Profil zur Kontrolle öffnen</li>
        <li>Mit dem <i class="bi bi-arrow-clockwise"></i>-Button neben dem Feld die aktuelle Spielstärke direkt abrufen — der Wert wird in <em>Spielstärke Tischtennis</em> eingetragen und gespeichert</li>
      </ol>
      <p>In der Spielerübersicht erscheint bei Spielern mit hinterlegter RatingsCentral-ID ein <i class="bi bi-arrow-clockwise"></i>-Symbol neben der TT-Spielstärke — ein Klick darauf aktualisiert den Wert direkt in der Tabelle.</p>
      <p>Über den Button <strong>TT RC Abgleich</strong> (rechts in der Toolbar) werden alle Spieler mit hinterlegter RatingsCentral-ID nacheinander abgeglichen. Fortschritt und Ergebnis werden direkt angezeigt.</p>

      <h5 class="mt-3">Import per CSV/XLSX</h5>
      <p>Über <strong>Spieler importieren</strong> können Spieler per Datei massenweise angelegt werden. Die Vorlage ist über den gleichnamigen Button herunterladbar. Spalten: Name, Vorname, Verein, Geschlecht, Lizenznummer, E-Mail, Spielstärke, Spielstärke Tischtennis, Spielstärke Tennis, Spielstärke Fußball, RatingsCentral-ID.</p>

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
        <li>Der Name wird automatisch aus den Nachnamen gebildet, kann aber angepasst werden</li>
        <li>Die <strong>Spielstärke</strong> ergibt sich automatisch als Summe der Einzelspielstärken je Sportart</li>
      </ol>

      <h5 class="mt-3">Doppel bearbeiten</h5>
      <p>Das Bearbeiten-Modal zeigt Name, automatische Spielstärke und einen <strong>Bewerbe</strong>-Tab mit allen Turnier- und Bewerbszuordnungen des Doppels.</p>

      <h5 class="mt-3">Doppel einem Bewerb zuweisen</h5>
      <p>Auf der Bewerbsseite eines Doppelbewerbs kann über die Suchleiste ein Doppel aus dem Register hinzugefügt werden.</p>
      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Jeder Einzelspieler darf pro Bewerb nur in <strong>einem</strong> Doppel teilnehmen. Beim Hinzufügen wird dies automatisch geprüft.
      </div>

      <h5 class="mt-3">Doppel aus Nennungen bilden</h5>
      <p>Melden sich Spieler für einen Doppelbewerb über das öffentliche Anmeldeformular an, erscheinen sie nach Admin-Bestätigung im Abschnitt <em>Bestätigte Nennungen (noch ohne Partner)</em> auf der Bewerbsseite. Der Admin kann dort zwei Spieler über <strong>Doppel bilden</strong> direkt paaren und dem Bewerb zuweisen.</p>
    </section>

    <!-- Teams -->
    <section id="teams" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Teams &amp; Teambewerb</h2>
      <p>Teams sind Mannschaften aus mehreren Spielern. Sie werden im <strong>Spielerregister</strong> unter dem Tab <em>Teams</em> verwaltet und können Teambewerben zugewiesen werden.</p>

      <h5 class="mt-3">Team anlegen und verwalten</h5>
      <ol>
        <li>Im Spielerregister auf den Tab <strong>Teams</strong> wechseln</li>
        <li>Über <strong>Neues Team erstellen</strong> einen Teamnamen vergeben und optional Spieler direkt hinzufügen</li>
        <li>Das Bearbeiten-Modal zeigt zwei Tabs: <strong>Mitglieder</strong> (Spieler hinzufügen/entfernen) und <strong>Bewerbe</strong> (alle Bewerbs-Zuordnungen mit Turniername)</li>
      </ol>
      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Ein Spieler kann nicht aus einem Team entfernt werden, wenn er in einem gespeicherten Spielergebnis (Duel) des Teams eingetragen ist.
      </div>

      <h5 class="mt-3">Teambewerb konfigurieren</h5>
      <p>Beim Anlegen oder in den Einstellungen eines Bewerbs den Typ <strong>Teambewerb</strong> wählen. Die wichtigste Einstellung:</p>
      <ul>
        <li><strong>Spiele pro Team</strong> — Legt fest, wie viele Einzel-Duelle pro Mannschaftsspiel ausgetragen werden (z.B. 4 = vier Einzelspiele). Bei <strong>0</strong> wird das Gesamtergebnis direkt eingegeben (ohne Duelle); bei <strong>1</strong> gibt es ein Duel ohne Spielerauswahl. Diese Einstellung ist nach dem ersten Auslosen gesperrt.</li>
      </ul>

      <h5 class="mt-3">Teams einem Bewerb zuweisen</h5>
      <p>Auf der Bewerbsseite können im Setup-Modus Teams aus dem Register hinzugefügt werden.</p>

      <h5 class="mt-3">Ergebnisse bei Teambewerben</h5>
      <p>Wenn <em>Spiele pro Team</em> größer 0 ist, wird jedes Mannschaftsspiel in <strong>Einzel-Duelle</strong> aufgeteilt:</p>
      <ul>
        <li>Direkt unter jedem Spiel erscheint eine Eingabetabelle: links die Spielerauswahl aus Team 1 (rechtsbündig), in der Mitte das Ergebnis des Duells, rechts die Spielerauswahl aus Team 2</li>
        <li>Ab <em>Spiele pro Team ≥ 2</em> wird pro Zeile eine Spielerauswahl eingeblendet; zusätzlich steht der Eintrag <strong>Doppel</strong> zur Verfügung — für Doppelspiele innerhalb eines Mannschaftskampfs. Doppel kann in mehreren Zeilen gleichzeitig gewählt werden.</li>
        <li>Das <strong>Gesamtergebnis</strong> (Anzahl gewonnener Duelle) wird in der Kopfzeile des Spiels angezeigt — als <code>—:—</code> solange noch nichts gespeichert ist</li>
        <li>Nach dem Speichern wird das Gesamtergebnis automatisch aus den Duel-Ergebnissen berechnet</li>
        <li>Gäste und Viewer sehen die Einzel-Ergebnisse unterhalb der Gruppenstand-Tabelle als read-only Ansicht</li>
      </ul>

      <h5 class="mt-3">Gruppenstand bei Teambewerben</h5>
      <p>Ab <em>Spiele pro Team ≥ 2</em> werden in der Gruppenstand-Tabelle zusätzliche Spalten angezeigt:</p>
      <ul>
        <li><strong>V (Einzel)</strong> — Verhältnis der gewonnenen zu verlorenen Einzelduelle (gesamt)</li>
        <li><strong>+/- (Einzel)</strong> — Differenz aus gewonnenen und verlorenen Einzelduellen</li>
      </ul>
      <p>Tiebreaker-Reihenfolge bei Punktegleichstand im Teambewerb mit Einzelduellen:</p>
      <ol class="small">
        <li>Direktvergleich (H2H): Punkte → Mannschaftsdifferenz → Mannschafts-Plus → Einzeldifferenz (H2H) → Einzel-Plus (H2H)</li>
        <li>Gesamte Mannschaftsdifferenz</li>
        <li>Gesamt-Einzeldifferenz</li>
        <li>Gesamt-Mannschafts-Plus</li>
        <li>Gesamt-Einzel-Plus</li>
        <li>Tabellengleichstand — Positionen manuell festlegen</li>
      </ol>

      <h5 class="mt-3">PDFs bei Teambewerben</h5>
      <ul>
        <li><strong>Gruppenplan-PDF</strong> — Zeigt nach jeder Gruppenstand-Tabelle die Spielpaarungen mit den eingetragenen Einzel-Spielernamen und -Ergebnissen</li>
        <li><strong>Spielkarten-PDF</strong> — Jede Karte enthält die Teamnamen und so viele leere Zeilen wie <em>Spiele pro Team</em> angegeben — für die händische Eintragung vor Ort (3 Karten pro Seite statt 6 bei Einzelbewerben)</li>
      </ul>
    </section>

    <!-- Auslosung -->
    <section id="auslosung" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Auslosung</h2>

      <h5 id="gruppenphase" class="mt-3">Gruppenphase</h5>
      <p>Voraussetzung: Modus <em>Gruppenphase + KO</em>, mindestens so viele Teilnehmer wie eine Gruppenanzahl × Gruppengröße (3–10 Spieler/Teams pro Gruppe).</p>
      <ol>
        <li>Klicke auf <strong>Gruppen auslosen</strong> — Teilnehmer werden nach Spielstärke gereiht und gleichmäßig auf die Gruppen verteilt</li>
        <li>Die Gruppenreihenfolge kann per Drag &amp; Drop angepasst werden (<strong>Gruppen neu ordnen</strong>)</li>
        <li>Alle Gruppenspiele werden automatisch als Round-Robin-Spielplan erzeugt</li>
        <li>Ergebnisse erfassen → Tabelle wird automatisch berechnet</li>
        <li>Wenn alle Gruppenspiele eingetragen sind, erscheint <strong>KO-Runde auslosen</strong></li>
      </ol>
      <p>Die Gruppenplatzierung (Sieg=2 Pkt., Unentschieden=1 Pkt., Niederlage=0 Pkt.) bestimmt den Aufstieg. Tiebreaker-Reihenfolge bei Punktegleichstand:</p>
      <ol class="small">
        <li>Direkter Vergleich der punktegleichen Spieler: Punkte → Differenz → Plus</li>
        <li>Gesamte Differenz aller Gruppenspiele</li>
        <li>Gesamt-Plus</li>
        <li>Tabellengleichstand — Positionen manuell festlegen</li>
      </ol>
      <div class="alert alert-info small mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Solange ein offener Tabellengleichstand besteht, ist die Schaltfläche <strong>KO-Phase auslosen</strong> gesperrt. Der Gleichstand muss zuerst über <strong>Tabellengleichstand – Positionen festlegen</strong> aufgelöst werden.
      </div>

      <h5 id="ko" class="mt-3">KO-Runde (nach Gruppenphase)</h5>
      <p>Nach Abschluss der Gruppenphase werden die qualifizierten Teilnehmer in ein KO-Bracket ausgelost. Gruppensieger werden gegen Gruppenzweite aus anderen Gruppen gesetzt.</p>

      <h5 id="ko-direkt" class="mt-3">Nur KO-Runde</h5>
      <p>Im Modus <em>Nur KO-Runde</em> entfällt die Gruppenphase. Alle zugewiesenen Teilnehmer kommen direkt in ein gesetztes KO-Bracket; Freilose werden automatisch weitergerückt.</p>

      <h5 id="doppel-ko" class="mt-3">Doppel-KO</h5>
      <p>Im Doppel-KO scheidet ein Spieler erst nach zwei Niederlagen aus. Das Bracket besteht aus:</p>
      <ul>
        <li><strong>Winners Bracket (WB)</strong> — Spieler ohne Niederlage</li>
        <li><strong>Losers Bracket (LB)</strong> — Spieler mit genau einer Niederlage; LB-Runden wechseln zwischen Minor (neue WB-Verlierer) und Major (reine LB-Spiele)</li>
        <li><strong>Grand Final</strong> — WB-Sieger vs. LB-Sieger</li>
      </ul>
      <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Nach jeder Ergebnisänderung werden alle abhängigen Spieler automatisch neu berechnet. Es ist nicht nötig, das Bracket manuell zu aktualisieren.
      </div>
    </section>

    <!-- Ergebnisse -->
    <section id="ergebnisse" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Ergebnisse erfassen</h2>

      <h5 class="mt-3">Einzel- und Doppelbewerbe</h5>
      <ul>
        <li><strong>Gruppenspiele</strong> — Score-Felder neben jedem Spiel; mit <kbd>Enter</kbd> oder Klick auf <strong>Speichern</strong> bestätigen. Mehrere Ergebnisse können per <strong>Alle speichern</strong> gleichzeitig übermittelt werden.</li>
        <li><strong>KO-Spiele</strong> — Score-Felder im KO-Raster; nach dem Speichern rückt der Gewinner automatisch vor.</li>
        <li><strong>Ergebnis korrigieren</strong> — Mit dem ✕-Button kann ein eingetragenes Ergebnis gelöscht werden; abhängige Runden werden zurückgesetzt.</li>
      </ul>

      <h5 class="mt-3">Teambewerbe</h5>
      <p>Wenn ein Teambewerb mit <em>Spiele pro Team &gt; 0</em> konfiguriert ist, wird für jedes Mannschaftsspiel eine Duel-Tabelle eingeblendet:</p>
      <ul>
        <li>Pro Zeile: Spielerauswahl Team 1 (links) — Ergebnis des Duells (Mitte) — Spielerauswahl Team 2 (rechts)</li>
        <li>Ab <em>Spiele pro Team ≥ 2</em> steht zusätzlich der Eintrag <strong>Doppel</strong> zur Verfügung (für Doppelspiele); Doppel kann in mehreren Zeilen gleichzeitig gewählt werden</li>
        <li>Das <strong>Gesamtergebnis</strong> (Anzahl gewonnener Duelle) ergibt sich automatisch und wird in der Kopfzeile des Spiels angezeigt</li>
        <li>Bei KO-Spielen ist die Duel-Tabelle über das <strong>Duelle</strong>-Element ausklappbar</li>
        <li>Bei <em>Spiele pro Team = 0</em> wird nur das Gesamtergebnis direkt eingetragen (wie bei Einzelbewerben)</li>
      </ul>

      <h5 class="mt-3">Spielkarten drucken</h5>
      <p>Über <strong>Spielkarten PDF</strong> können Papier-Ergebniszettel für alle offenen Spiele gedruckt werden. Bei Teambewerben enthält jede Karte die Teamnamen und leere Zeilen für die Einzel-Ergebnisse.</p>
    </section>

    <!-- Nennungen -->
    <section id="nennungen" class="mb-5">
      <h2 class="h4 border-bottom pb-2">Nennungen (öffentliches Anmeldeformular)</h2>
      <p>Spieler können sich über die öffentliche Turnierseite selbst anmelden, ohne einen Account zu benötigen.</p>

      <h5 class="mt-3">Ablauf für Spieler</h5>
      <ol>
        <li>Turnierseite aufrufen → <strong>Anmelden</strong></li>
        <li>Name, Kontaktdaten und gewünschte Bewerbe auswählen → Absenden</li>
        <li>Bei Doppelbewerben kann optional ein <strong>Gewünschter Partner</strong> angegeben werden</li>
        <li>Die Nennung erscheint als <em>ausstehend</em> beim Admin</li>
        <li>Sobald der Admin alle Bewerbe bestätigt hat, wird automatisch ein <strong>Verwaltungslink</strong> per E-Mail verschickt (60 Minuten gültig)</li>
        <li>Über diesen Link kann die Nennung zurückgezogen oder ein Änderungsantrag gestellt werden</li>
      </ol>

      <h5 class="mt-3">Ablauf für Admins (Einzelbewerbe)</h5>
      <ul>
        <li>Neue Nennungen erscheinen auf der Turnierseite unter <em>Ausstehende Nennungen</em></li>
        <li>Bestätigung: Einzeln pro Bewerb oder alle auf einmal mit <strong>Alle bestätigen</strong></li>
        <li>Der Verwaltungslink wird automatisch versendet, sobald alle Bewerbe einer Nennung entschieden sind</li>
        <li>Änderungsanträge erscheinen unter <em>Änderungsanträge</em></li>
      </ul>

      <h5 class="mt-3">Ablauf für Admins (Doppelbewerbe)</h5>
      <ol>
        <li>Nennung bestätigen → Spieler erscheint im Abschnitt <em>Bestätigte Nennungen (noch ohne Partner)</em></li>
        <li>Zwei Spieler über <strong>Doppel bilden</strong> paaren → Doppel wird angelegt und dem Bewerb zugewiesen</li>
      </ol>
      <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Zieht ein Spieler aus einem Doppelbewerb zurück, wird das gesamte Doppel aus dem Bewerb entfernt. Die Nennung des Partners wird auf <em>ausstehend</em> zurückgesetzt.
      </div>

      <h5 class="mt-3">Nennung nachträglich bearbeiten</h5>
      <p>Über den Verwaltungslink können Spieler die Nennung zurückziehen, Bewerbe hinzufügen/abmelden oder den Wunschpartner aktualisieren. Alle Änderungen (außer dem Partner-Namen) müssen vom Admin bestätigt werden.</p>

      <h5 class="mt-3">Verwaltungslink anfordern</h5>
      <p>Über den Menüpunkt <strong>Nennungen</strong> (Menü oben) gibt es zwei Bereiche:</p>
      <ul>
        <li><strong>Nennung für Turnier abgeben</strong> — Listet alle Turniere mit offener Nennungsfrist direkt mit Link zum Nennungsformular</li>
        <li><strong>Bestehende Nennung bearbeiten</strong> — Mit der E-Mail-Adresse der Nennung kann ein neuer Verwaltungslink angefordert werden (60 Minuten gültig)</li>
      </ul>

      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Ohne konfigurierte E-Mail-Einstellungen (<code>MAIL_HOST</code>) werden Verwaltungslinks direkt im Admin-Interface als Flash-Nachricht angezeigt.
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
              <td>Alle Gruppen mit Tabelle und Spielplan. Bei Teambewerben zusätzlich die Spielpaarungen mit eingetragenen Einzel-Spielernamen und -Ergebnissen.</td>
            </tr>
            <tr>
              <td>KO-Bracket PDF</td>
              <td>Bewerbsseite</td>
              <td>KO-Raster (Querformat)</td>
            </tr>
            <tr>
              <td>Spielkarten PDF</td>
              <td>Bewerbsseite</td>
              <td>Papier-Ergebniszettel für alle offenen Spiele. Bei Teambewerben mit konfigurierten Duellen enthält jede Karte leere Zeilen für die Einzelergebnisse (3 Karten/Seite statt 6).</td>
            </tr>
            <tr>
              <td>Bewerbsspieler PDF/CSV</td>
              <td>Bewerbsseite</td>
              <td>Teilnehmerliste des Bewerbs mit Spielstärke</td>
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
