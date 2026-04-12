<?php
/**
 * BeSix CAD — API endpoint
 * Soubor: cad.besix.cz/api/cad.php
 *
 * Autonomní API pro CAD data (razítka, metadata výkresů, symboly).
 * Sdílí auth session s board.besix.cz přes cookie domain=.besix.cz
 *
 * Akce:
 *   GET  ?action=load_templates                  — načte šablony razítek uživatele
 *   POST ?action=save_template                   — uloží/aktualizuje šablonu razítka
 *   DELETE ?action=delete_template&id=X           — smaže šablonu razítka
 *
 *   GET  ?action=load_metadata&project_id=X       — načte metadata výkresů projektu
 *   POST ?action=save_metadata                    — uloží/aktualizuje metadata výkresu
 *   DELETE ?action=delete_metadata&id=X            — smaže metadata výkresu
 *
 *   GET  ?action=load_symbols                     — načte symboly (výchozí + vlastní)
 *   POST ?action=save_symbol                      — uloží vlastní symbol
 *   DELETE ?action=delete_symbol&id=X              — smaže vlastní symbol
 */

// ── Konfigurace ─────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

// CORS pro cross-subdomain (board.besix.cz ↔ cad.besix.cz)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://cad.besix.cz', 'https://board.besix.cz', 'http://localhost'];
if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Session (sdílená s board.besix.cz) ──────────────────────────────────────
session_set_cookie_params([
    'lifetime' => 604800,      // 7 dní
    'path'     => '/',
    'domain'   => '.besix.cz', // sdílená cookie pro celý ekosystém
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
]);
session_start();

// ── DB připojení (sdílená databáze s board.besix.cz) ────────────────────────
// UPRAV: přihlašovací údaje dle cesky-hosting.cz
$DB_HOST = 'localhost';
$DB_NAME = 'besix_db';       // stejná DB jako board.besix.cz
$DB_USER = 'besix_user';
$DB_PASS = 'CHANGE_ME';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'DB nedostupná']);
    exit;
}

// ── Auth kontrola ───────────────────────────────────────────────────────────
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
    exit;
}

// ── Router ──────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ════════════════════════════════════════════════════════════════
        // ŠABLONY RAZÍTEK
        // ════════════════════════════════════════════════════════════════

        case 'load_templates':
            if ($method !== 'GET') { respond(405, 'Method Not Allowed'); }
            $stmt = $pdo->prepare('SELECT id, name, logo_data, fields_json, paper_size, is_default, updated_at FROM cad_title_block_templates WHERE user_id = ? ORDER BY is_default DESC, updated_at DESC');
            $stmt->execute([$userId]);
            $templates = $stmt->fetchAll();
            // Decode fields_json pro frontend
            foreach ($templates as &$t) {
                $t['fields'] = json_decode($t['fields_json'], true);
                unset($t['fields_json']);
            }
            respond(200, null, ['templates' => $templates]);
            break;

        case 'save_template':
            if ($method !== 'POST') { respond(405, 'Method Not Allowed'); }
            $input = getJsonInput();
            $id         = $input['id'] ?? null;
            $name       = trim($input['name'] ?? 'Bez názvu');
            $logoData   = $input['logo_data'] ?? null;
            $fieldsJson = json_encode($input['fields'] ?? [], JSON_UNESCAPED_UNICODE);
            $paperSize  = $input['paper_size'] ?? 'A3';
            $isDefault  = $input['is_default'] ?? 0;

            // Pokud is_default, odeber default z ostatních
            if ($isDefault) {
                $pdo->prepare('UPDATE cad_title_block_templates SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
            }

            if ($id) {
                // Update existující (ověř vlastnictví)
                $stmt = $pdo->prepare('UPDATE cad_title_block_templates SET name = ?, logo_data = ?, fields_json = ?, paper_size = ?, is_default = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$name, $logoData, $fieldsJson, $paperSize, $isDefault, $id, $userId]);
                if ($stmt->rowCount() === 0) { respond(404, 'Šablona nenalezena'); }
            } else {
                // Insert nová
                $stmt = $pdo->prepare('INSERT INTO cad_title_block_templates (user_id, name, logo_data, fields_json, paper_size, is_default) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $name, $logoData, $fieldsJson, $paperSize, $isDefault]);
                $id = $pdo->lastInsertId();
            }
            respond(200, null, ['id' => (int)$id]);
            break;

        case 'delete_template':
            if ($method !== 'DELETE') { respond(405, 'Method Not Allowed'); }
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { respond(400, 'Chybí id'); }
            $stmt = $pdo->prepare('DELETE FROM cad_title_block_templates WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() === 0) { respond(404, 'Šablona nenalezena'); }
            respond(200);
            break;

        // ════════════════════════════════════════════════════════════════
        // METADATA VÝKRESŮ
        // ════════════════════════════════════════════════════════════════

        case 'load_metadata':
            if ($method !== 'GET') { respond(405, 'Method Not Allowed'); }
            $projectId = (int)($_GET['project_id'] ?? 0);
            if (!$projectId) { respond(400, 'Chybí project_id'); }

            // Ověř, že user je členem projektu (sdílená tabulka project_members)
            if (!isProjectMember($pdo, $userId, $projectId)) {
                respond(403, 'Nemáš přístup k projektu');
            }

            $stmt = $pdo->prepare('
                SELECT dm.id, dm.file_name, dm.drawing_name, dm.scale, dm.paper_size,
                       dm.thumbnail, dm.file_hash, dm.template_id, dm._ts,
                       dm.created_at, dm.updated_at, u.name as author_name
                FROM cad_drawing_metadata dm
                JOIN users u ON u.id = dm.user_id
                WHERE dm.project_id = ?
                ORDER BY dm.updated_at DESC
            ');
            $stmt->execute([$projectId]);
            respond(200, null, ['drawings' => $stmt->fetchAll()]);
            break;

        case 'save_metadata':
            if ($method !== 'POST') { respond(405, 'Method Not Allowed'); }
            $input = getJsonInput();
            $id          = $input['id'] ?? null;
            $projectId   = (int)($input['project_id'] ?? 0);
            $fileName    = trim($input['file_name'] ?? '');
            $drawingName = trim($input['drawing_name'] ?? '');
            $scale       = $input['scale'] ?? '1:100';
            $paperSize   = $input['paper_size'] ?? 'A3';
            $thumbnail   = $input['thumbnail'] ?? null;
            $fileHash    = $input['file_hash'] ?? null;
            $templateId  = $input['template_id'] ?? null;
            $ts          = $input['_ts'] ?? round(microtime(true) * 1000);

            if (!$projectId || !$fileName) { respond(400, 'Chybí project_id nebo file_name'); }
            if (!isProjectMember($pdo, $userId, $projectId)) {
                respond(403, 'Nemáš přístup k projektu');
            }

            if ($id) {
                $stmt = $pdo->prepare('
                    UPDATE cad_drawing_metadata
                    SET file_name = ?, drawing_name = ?, scale = ?, paper_size = ?,
                        thumbnail = ?, file_hash = ?, template_id = ?, _ts = ?
                    WHERE id = ? AND project_id = ?
                ');
                $stmt->execute([$fileName, $drawingName, $scale, $paperSize, $thumbnail, $fileHash, $templateId, $ts, $id, $projectId]);
                if ($stmt->rowCount() === 0) { respond(404, 'Výkres nenalezen'); }
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO cad_drawing_metadata
                    (project_id, user_id, file_name, drawing_name, scale, paper_size, thumbnail, file_hash, template_id, _ts)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$projectId, $userId, $fileName, $drawingName, $scale, $paperSize, $thumbnail, $fileHash, $templateId, $ts]);
                $id = $pdo->lastInsertId();
            }
            respond(200, null, ['id' => (int)$id]);
            break;

        case 'delete_metadata':
            if ($method !== 'DELETE') { respond(405, 'Method Not Allowed'); }
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { respond(400, 'Chybí id'); }
            // Smazat může vlastník výkresu nebo admin/owner projektu
            $stmt = $pdo->prepare('SELECT project_id, user_id FROM cad_drawing_metadata WHERE id = ?');
            $stmt->execute([$id]);
            $drawing = $stmt->fetch();
            if (!$drawing) { respond(404, 'Výkres nenalezen'); }
            if ((int)$drawing['user_id'] !== (int)$userId) {
                $role = getProjectRole($pdo, $userId, $drawing['project_id']);
                if (!in_array($role, ['owner', 'admin'])) {
                    respond(403, 'Nemáš oprávnění smazat tento výkres');
                }
            }
            $pdo->prepare('DELETE FROM cad_drawing_metadata WHERE id = ?')->execute([$id]);
            respond(200);
            break;

        // ════════════════════════════════════════════════════════════════
        // SYMBOLY / BLOKY
        // ════════════════════════════════════════════════════════════════

        case 'load_symbols':
            if ($method !== 'GET') { respond(405, 'Method Not Allowed'); }
            // Výchozí symboly (pro všechny)
            $defaults = $pdo->query('SELECT id, name, category, svg_data, width_mm, height_mm FROM cad_default_symbols ORDER BY category, name')->fetchAll();
            // Vlastní symboly uživatele
            $custom = $pdo->prepare('SELECT id, name, category, svg_data, width_mm, height_mm FROM cad_user_symbols WHERE user_id = ? ORDER BY category, name');
            $custom->execute([$userId]);
            respond(200, null, [
                'default_symbols' => $defaults,
                'user_symbols'    => $custom->fetchAll()
            ]);
            break;

        case 'save_symbol':
            if ($method !== 'POST') { respond(405, 'Method Not Allowed'); }
            $input = getJsonInput();
            $id       = $input['id'] ?? null;
            $name     = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? 'obecné');
            $svgData  = $input['svg_data'] ?? '';
            $widthMm  = $input['width_mm'] ?? null;
            $heightMm = $input['height_mm'] ?? null;

            if (!$name || !$svgData) { respond(400, 'Chybí name nebo svg_data'); }

            if ($id) {
                $stmt = $pdo->prepare('UPDATE cad_user_symbols SET name = ?, category = ?, svg_data = ?, width_mm = ?, height_mm = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$name, $category, $svgData, $widthMm, $heightMm, $id, $userId]);
                if ($stmt->rowCount() === 0) { respond(404, 'Symbol nenalezen'); }
            } else {
                $stmt = $pdo->prepare('INSERT INTO cad_user_symbols (user_id, name, category, svg_data, width_mm, height_mm) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $name, $category, $svgData, $widthMm, $heightMm]);
                $id = $pdo->lastInsertId();
            }
            respond(200, null, ['id' => (int)$id]);
            break;

        case 'delete_symbol':
            if ($method !== 'DELETE') { respond(405, 'Method Not Allowed'); }
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { respond(400, 'Chybí id'); }
            $stmt = $pdo->prepare('DELETE FROM cad_user_symbols WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() === 0) { respond(404, 'Symbol nenalezen'); }
            respond(200);
            break;

        default:
            respond(400, "Neznámá akce: $action");
    }

} catch (PDOException $e) {
    error_log("BeSix CAD API error: " . $e->getMessage());
    respond(500, 'Chyba databáze');
} catch (Exception $e) {
    error_log("BeSix CAD API error: " . $e->getMessage());
    respond(500, 'Neočekávaná chyba');
}

// ── Helpery ─────────────────────────────────────────────────────────────────

function respond(int $code, ?string $error = null, array $extra = []) {
    http_response_code($code);
    $response = ['success' => $code >= 200 && $code < 300];
    if ($error) $response['error'] = $error;
    echo json_encode(array_merge($response, $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        // Fallback: try form data
        $data = $_POST;
        if (isset($data['data'])) $data = json_decode($data['data'], true) ?? $data;
    }
    return $data ?: [];
}

function isProjectMember(PDO $pdo, int $userId, int $projectId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM project_members WHERE user_id = ? AND project_id = ? LIMIT 1');
    $stmt->execute([$userId, $projectId]);
    return (bool)$stmt->fetch();
}

function getProjectRole(PDO $pdo, int $userId, int $projectId): ?string {
    $stmt = $pdo->prepare('SELECT role FROM project_members WHERE user_id = ? AND project_id = ? LIMIT 1');
    $stmt->execute([$userId, $projectId]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : null;
}
