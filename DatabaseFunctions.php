<?php

// TODO add PHPDoc comments
$databasePDO = null;
$tableName = "tagebuch";

/*
 * function DBconnect();
 * function DBend();
 * function getEntries();
 * function getEntryById($id);
 * function newEntry($newEntryDataset);
 * 
 * 
 * function modifyEntry($ID, $newEntryDataset);
 * function deleteEntry($ID);
 * function changeState($ID, $state);
 */

/**
 * Baut Verbindung zur lokalen(!) SQL Datenbank auf mit PDO: "PHP Data Object"
 *
 * @return = "true" aus bei Erfolg, sonst "false"
 */
function DBconnect()
{
    global $useDB; // "global" gibt an dass Variable ausserhalb der Funktion schon existiert und verwendet werden soll
    global $databasePDO;
    if (! $useDB) {
        return true; // falls keine Datenbank verwendet werden soll, ist Verbindungsaufbau immer erfolgreich
    }

    // Daten für SQL-Account
    $user = "tagebuchUser";
    $pass = "YI6iqDwpvzMJMuAP";

    $dbname = "db"; // Name der Datenbank

    // mit Datenbank verbinden, ohne Absturz beim Scheitern
    try {
        $databasePDO = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $user, $pass);
    } catch (PDOException $e) {
        echo "Konnte nicht zur Datenbank verbinden!<br>" . $e->getMessage() . "<br>";
    }

    // Falls $databasePDO leer ist, dann ist Verbindung gescheitert: mit false beenden
    if (! $databasePDO) {
        return false;
    }

    $databasePDO->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // PDO Einstellung: soll Prepared Statements nicht nur emulieren sondern tatsächlich verwenden
    $databasePDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // PDO Einstellung: soll Exceptions werfen statt Abbruch&Fehler

    return true; // mit Erfolg quittieren
}

/**
 * Beendet Verbindung zur DB
 *
 * bei PDO's wird hier nur die Variable auf null gesetzt, mit anderen Bibliotheken wäre hier evtl. mehr zu tun
 */
function DBend()
{
    global $useDB;
    global $databasePDO;
    if (! $useDB)
        return;
    $databasePDO = null; // Standard wenn man PDO's beendet werden: einfach Referenz auf NULL setzen
}

/**
 * holt alle Einträge aus Datenbank, optional sortiert
 *
 * @param number $orderBy
 *            Sortierreihenfolge (optional): -1 für aufsteigend, 1 für absteigend, 0 ist unsortiert (Standard)
 * @return string[][]|NULL Array gefundener Einträge oder NULL bei Fehlern
 */
function getEntries($orderBy = 0)
{
    global $useDB;
    global $databasePDO;
    global $tableName;
    $queryReturn = null;

    // ohne Datenbanknutzung statisch hinterlegtes Array ausgeben
    if (! $useDB) {
        return getEntriesStatic();
    }

    // falls keine Verbindung aktiv, null zurückgeben
    if (! $databasePDO) {
        return null;
    }

    // je nach Sortierparameter SQL Abfrage auswählen und ausführen
    switch ($orderBy) {
        case - 1:
            $queryReturn = $databasePDO->query("SELECT * FROM $tableName ORDER BY Datum ASC");
            break;
        case 1:
            $queryReturn = $databasePDO->query("SELECT * FROM $tableName ORDER BY Datum DESC");
            break;
        default:
            $queryReturn = $databasePDO->query("SELECT * FROM $tableName");
            break;
    }

    // alle Einträge aus Suche zwischenspeichern und ausgeben
    $result = $queryReturn->fetchAll();
    return $result;
}

/**
 * holt alle Einträge zwischen 2 Daten
 *
 * @param string $dateFrom Datum Beginn
 * @param string $dateTo Datum Ende
 * @param number $orderBy
 *            Sortierreihenfolge (optional): -1 für aufsteigend, 1 für absteigend, 0 ist unsortiert (Standard)
 * @return string[][]|NULL Array gefundener Einträge oder NULL bei Fehlern
 */
function getEntriesByDateInterval($dateFrom, $dateTo, $orderBy = 0)
{
    global $useDB;
    global $databasePDO;
    global $tableName;

    // ohne Datenbanknutzung statisch hinterlegtes Array ausgeben
    if (! $useDB) {
        return getEntriesStatic();
    }

    // falls keine Verbindung aktiv, null zurückgeben
    if (! $databasePDO) {
        return null;
    }

    // wenn eines der Datums-Parameter leer, mit null beenden
    if (! $dateFrom || ! $dateTo) {
        return null;
    }
    
    // je nach Sortierparameter SQL Abfrage auswählen und ausführen
    switch ($orderBy) {
        case - 1:
            $queryReturn = $databasePDO->query("SELECT * FROM $tableName WHERE Datum BETWEEN '$dateFrom' AND '$dateTo' ORDER BY Datum ASC");
            break;
        case 1:
            $queryReturn = $databasePDO->query("SELECT * FROM $tableName WHERE Datum BETWEEN '$dateFrom' AND '$dateTo' ORDER BY Datum DESC");
            break;
        default:
            $queryReturn = $databasePDO->query("SELECT * FROM $tableName WHERE Datum BETWEEN '$dateFrom' AND '$dateTo' ");
            break;
    }
    
    // alle Einträge aus Suche zwischenspeichern und ausgeben
    $result = $queryReturn->fetchAll();
    return $result;
}

/**
 * gibt Einträge aus deren Feld $field den String $searchString enthält 
 * 
 * @param string $field Tabellenfeld in dem gesucht wird
 * @param string $searchString String nach dem gesucht wird
 * @return string[][]|NULL Array gefundener Einträge oder NULL bei Fehlern
 */
function getEntriesByFilter($field, $searchString)
{
    global $useDB;
    global $databasePDO;
    global $tableName;

    // ohne Datenbanknutzung statisch hinterlegtes Array ausgeben
    if (! $useDB) {
        return getEntriesStatic();
    }
    
    // falls keine Verbindung aktiv, null zurückgeben
    if (! $databasePDO) {	
        return null;
    }

    // wenn eines der Parameter leer, mit null beenden
    if (! $field || ! $searchString) {
        return null;
    }
   
    // SQl-Befehl parametrisiert (vermeidet "SQL-Injection", Parameter können nicht mehr als neue SQL-Anweisungen verarbeitet werden
    $insertStatement =  "SELECT * FROM $tableName WHERE $field LIKE :searchString";

    // SQL-Befehl vorbereiten
    $preparedStatement = $databasePDO->prepare($insertStatement);
	
	//Parameter separat binden, nötig zur korrekten Behandlung von %-wildcards
    $preparedStatement->bindValue(':searchString', '%' . $searchString . '%');
	
    // vorbereiteten SQL-Befehl ausführen
    $preparedStatement->execute();

	//echo $preparedStatement->debugDumpParams(); //gibt Debug-Werte der vorbereiteten SQL-Statements aus 
	
    // alle Einträge aus Suche zwischenspeichern und ausgeben
    $result = $preparedStatement->fetchAll();

    return $result; 
}


/**
 * gibt einen Eintrag mit ID=$ID aus, falls dieser existiert, sonst null
 * @param integer $ID ID nach der gesucht wird
 * @return string[][]|NULL 
 */
function getEntryById($ID)
{
    global $useDB;
    global $databasePDO;
    global $tableName;

    // ohne Datenbanknutzung statisch hinterlegten Eintrag ausgeben
    if (! $useDB) {
        return getEntryByIDStatic($ID);
    }
    
    // falls keine Verbindung aktiv, null zurückgeben
    if (! $databasePDO) {
        return null;
    }

    // Abbruch falls $ID-Parameter keine Zahl ist
    if (! is_numeric($ID))
        return null; 

    //SQL Abfrage zur ID-Suche ausführen
    $SQLresult = $databasePDO->query("SELECT * FROM $tableName WHERE ID=$ID");
    
    // Eintrag aus Suche zwischenspeichern und ausgeben
    $result = $SQLresult->fetch();
    return $result;
}


/**
 * neuen Eintrag anlegen
 * @param array $newEntryDataset Array mit Daten des neuen Eintragest
 * @return NULL|number gibt bei Erfolg ID des neu angelegten Eintrages aus, sonst null
 */
function newEntry($newEntryDataset)
{
    global $useDB;
    global $databasePDO;
    global $tableName;
    
    // ohne Datenbanknutzung statisch hinterlegten Wert ausgeben, ID des ersten statischen Eintrages
    if (! $useDB) {
        return getEntriesStatic()[0]['ID'];
    }
    
    // falls keine Verbindung aktiv, null zurückgeben
    if (! $databasePDO) {
        return null;
    }

    // SQl-Befehl parametrisiert (vermeidet "SQL-Injection", Parameter können nicht mehr als neue SQL_Anweisungen verarbeitet werden
    $insertStatement = " INSERT INTO $tableName (Datum, Stunden, Beschreibung, Fehlt, Spaet, Frueh, Unruhe, Anmerkung, Hausaufgaben, Kommentar, Uebertragen)
                                        VALUES( :datum, :stunden , :beschreibung, :fehlt, :spaet, :frueh, :unruhe, :anmerkung, :hausaufgaben, :kommentar, :uebertragen )";

    // SQL-Befehl vorbereiten
    $preparedStatement = $databasePDO->prepare($insertStatement);

    // vorbereiteten SQL-Befehl parametrisiert ausführen
    $preparedStatement->execute([
        ':datum' => $newEntryDataset['Datum'],
        ':stunden' => $newEntryDataset['Stunden'],
        ':beschreibung' => $newEntryDataset['Beschreibung'],
        ':fehlt' => $newEntryDataset['Fehlt'],
        ':spaet' => $newEntryDataset['Spaet'],
        ':frueh' => $newEntryDataset['Frueh'],
        ':unruhe' => $newEntryDataset['Unruhe'],
        ':anmerkung' => $newEntryDataset['Anmerkung'],
        ':hausaufgaben' => $newEntryDataset['Hausaufgaben'],
        ':kommentar' => $newEntryDataset['Kommentar'],
        ':uebertragen' => $newEntryDataset['Uebertragen']
    ]);
    
    // gibt ID des neuen Elementes zurück (höchste ID in der Tabelle, da für ID Autoincrement aktiv ist)
    $maxIdQuery = $databasePDO->query("SELECT MAX(id) FROM $tableName");
    return $maxIdQuery->fetch()[0];
}

/**
 * ändert Eintrag mit $ID mit neuen Werten angegeben in $newEntryDataset (muss vollständig sein)
 * @param integer $ID ID des Eintrages der geändert wird
 * @param array $newEntryDataset Array mit neuem Datensatz
 * @return boolean "true" bei Erfolg, sonst "false"
 */
function modifyEntry($ID, $newEntryDataset)
{
    global $useDB;
    global $databasePDO;
    global $tableName;

    if (! $useDB) // Verarbeitung nur im Datenbankmodus
        return false;

    if (! $databasePDO) // DatenbankObjekt darf nicht leer sein
        return false;

    if (! is_numeric($ID)) // ID muss numerisch sein
        return false;

    // sichergehen dass Eintrag existiert: nach übergebener Eintrag-ID suchen
    $result = getEntryById($ID);
    if (! $result) {
        echo "Fehler: Keine Einträge zur ID '$ID' gefunden!";
        return false;
    }

    // SQl-Befehl-String mit Parametern
    $insertStatement = "UPDATE $tableName
        SET 
        Datum         =:datum, 
        Stunden       =:stunden, 
        Beschreibung  =:beschreibung,
        Fehlt         =:fehlt,
        Spaet         =:spaet,
        Frueh         =:frueh,
        Unruhe        =:unruhe,
        Anmerkung     =:anmerkung,
        Hausaufgaben  =:hausaufgaben,
        Kommentar     =:kommentar,
        Uebertragen   =:uebertragen
         WHERE
        ID           =:id";

    // SQL-Befehl vorbereiten
    $preparedStatement = $databasePDO->prepare($insertStatement);

    // vorbereiteten SQL-Befehl parametrisiert ausführen
    $preparedStatement->execute([
        ':datum' => $newEntryDataset['Datum'],
        ':stunden' => $newEntryDataset['Stunden'],
        ':beschreibung' => $newEntryDataset['Beschreibung'],
        ':fehlt' => $newEntryDataset['Fehlt'],
        ':spaet' => $newEntryDataset['Spaet'],
        ':frueh' => $newEntryDataset['Frueh'],
        ':unruhe' => $newEntryDataset['Unruhe'],
        ':anmerkung' => $newEntryDataset['Anmerkung'],
        ':hausaufgaben' => $newEntryDataset['Hausaufgaben'],
        ':kommentar' => $newEntryDataset['Kommentar'],
        ':uebertragen' => $newEntryDataset['Uebertragen'],
        ':id' => $ID
    ]);

    return true;
}


/**
 *  löscht Eintrag mit $ID
 * @param integer $ID ID des Eintrages der gelösch wird
 * @return boolean true bei erfolgreichem Löschen, sonst false
 */
function deleteEntry($ID)
{
    global $useDB;
    global $databasePDO;
    global $tableName;
    
    //ohne Datenbanknutzung wird erfolgreiches Löschen simuliert
    if (! $useDB) {
        return true;
    }

    // sichergehen dass Eintrag existiert: nach übergebener Eintrag-ID suchen
    $result = getEntryById($ID);
    if (! $result) {
        echo "Fehler: Keine Einträge zur ID '$ID' gefunden!";
        return false;
    }

    // SQl-Befehl-String mit Parametern
    $insertStatement = "DELETE FROM $tableName WHERE ID=:id";

    // SQL-Befehl vorbereiten
    $preparedStatement = $databasePDO->prepare($insertStatement);

    // vorbereiteten SQL-Befehl parametrisiert ausführen
    $preparedStatement->execute([
        ':id' => $ID
    ]);

    return true;
}

/**
 * ändert Status eines Eintrages
 * @param integer $ID ID des Eintrages
 * @param string $state neuer Status, mögliche Werte: 'ja', 'nein' und 'vorbereitet'
 * @return boolean true bei erfolgreicher Änderung, sonst false
 */
function changeState($ID, $state)
{
    global $useDB;
    global $databasePDO;
    global $tableName;
    
    //ohne Datenbanknutzung wird erfolgreiche Änderung simuliert
    if (! $useDB) {
        return true;
    }

    // sichergehen dass Eintrag existiert: nach übergebener Eintrag-ID suchen
    $result = getEntryById($ID);
    if (! $result) {
        echo "Fehler: Keine Einträge zur ID '$ID' gefunden!";
        return false;
    }

    // SQl-Befehl
    $insertStatement = "UPDATE $tableName SET Uebertragen = :uebertragen WHERE ID=:id";

    // SQL-Befehl vorbereiten
    $preparedStatement = $databasePDO->prepare($insertStatement);

    // vorbereiteten SQL-Befehl parametrisiert ausführen
    $preparedStatement->execute([
        ':id' => $ID,
        ':uebertragen' => $state
    ]);
    
    return true;
}

// Hilfsfunktionen

/**
 * gibt fest hinterlegte Werte aus um Datenbank zu simulieren
 * @return string[][] Array aus Tagebucheinträgen
 */
function getEntriesStatic()
{
    return [
        [
            "ID" => "6",
            "Datum" => "2019-11-30",
            "Stunden" => "1+4+6",
            "Beschreibung" => "Vorlesung 123, Inhalt",
            "Fehlt" => "alle da",
            "Spaet" => "bernt, bob, bernadine",
            "Frueh" => "lipshitz, loraine, hopital",
            "Unruhe" => "diesersatzkeinverb",
            "Anmerkung" => "China asshoe",
            "Hausaufgaben" => "Fasten, 2 Monate",
            "Kommentar" => "The beatings will continue until morale improves.",
            "Uebertragen" => "vorbereitet"
        ],
        [
            "ID" => "7",
            "Datum" => "2018-11-31",
            "Stunden" => "5+6+7",
            "Beschreibung" => "Vorlesung 123, Inhalt",
            "Fehlt" => "Aalle da",
            "Spaet" => "Abernt, bob, bernadine",
            "Frueh" => "Alipshitz, loraine, hopital",
            "Unruhe" => "Adiesersatzkeinverb",
            "Anmerkung" => "China asshoe",
            "Hausaufgaben" => "Fasten, 2 Monate",
            "Kommentar" => "The beatings will continue until morale improves.",
            "Uebertragen" => "ja"
        ],
        [
            "ID" => "8",
            "Datum" => "2011-8-31",
            "Stunden" => "5+7",
            "Beschreibung" => "Vorles23, Inhalt",
            "Fehlt" => "Aalle da",
            "Spaet" => "Abernt, bob, bernadine",
            "Frueh" => "Alipshitz, loraine, hopital",
            "Unruhe" => "Adiesersatzkeinverb",
            "Anmerkung" => "China asshoe",
            "Hausaufgaben" => "Fasten, 2 Monate",
            "Kommentar" => "The beatings will continue until morale improves.",
            "Uebertragen" => "ja"
        ],
        [
            "ID" => "9",
            "Datum" => "2010-8-31",
            "Stunden" => "5+7+11",
            "Beschreibung" => "Vorles2352453, Inhalt",
            "Fehlt" => "Aalle das",
            "Spaet" => "Abernt, blob, bernadine",
            "Frueh" => "Alipshitz, loraine, hopital",
            "Unruhe" => "Adiesersatzkeinverb",
            "Anmerkung" => "Timeline? Time LINE?? Time is not made out of lines. It is made out of cirlces. That is why clocks are round.",
            "Hausaufgaben" => "Fasten, 2 Monate",
            "Kommentar" => "The beatings will continue until morale improves.",
            "Uebertragen" => "nein"
        ]
    ];
}

/**
 * gibt fest hinterlegte Werte aus um Datenbank zu simulieren
 * @param integer $ID
 * @return Array erster statischer Eintrag mit geänderter ID
 */
function getEntryByIDStatic($ID)
{
    $ret = getEntriesStatic()[0];
    $ret["ID"] = $ID;
    return $ret;
}

?>
