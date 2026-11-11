<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config.php';

const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

function escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(string $url = 'index.php'): never
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function remove_stored_image(?string $path): void
{
    if ($path === null || !preg_match('~^uploads/[a-f0-9]{32}\.(jpg|png|webp|gif)$~D', $path)) {
        return;
    }

    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

function store_uploaded_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Không thể tải ảnh lên. Vui lòng thử lại.');
    }
    if (($file['size'] ?? 0) > MAX_IMAGE_SIZE) {
        throw new RuntimeException('Ảnh không được vượt quá 5 MB.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    if (!isset(IMAGE_TYPES[$mimeType])) {
        throw new RuntimeException('Định dạng ảnh không hợp lệ. Hãy chọn JPG, PNG, WEBP hoặc GIF.');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Không thể tạo thư mục lưu ảnh.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . IMAGE_TYPES[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Không thể lưu ảnh đã tải lên.');
    }

    return 'uploads/' . $filename;
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    preg_match_all('/./us', $value, $characters);
    return count($characters[0]);
}

$db = null;
$databaseError = null;

try {
    $db = db_connection();
} catch (PDOException $exception) {
    error_log('QLHocPhan database connection failed: ' . $exception->getMessage());
    $databaseError = 'Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra MySQL và cấu hình trong config.php.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db instanceof PDO) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        set_flash('error', 'Phiên biểu mẫu không hợp lệ. Vui lòng thử lại.');
        redirect_to();
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = strtoupper(trim((string) ($_POST['IDGV'] ?? '')));
            $fullname = trim((string) ($_POST['Fullname'] ?? ''));
            $sumClassInput = trim((string) ($_POST['SumClass'] ?? ''));
            $editingId = trim((string) ($_POST['editing_id'] ?? ''));

            if (!preg_match('/^[A-Z0-9_-]{2,20}$/D', $id)) {
                throw new RuntimeException('Mã giảng viên cần có 2–20 ký tự gồm chữ, số, dấu gạch ngang hoặc gạch dưới.');
            }
            if ($fullname === '' || text_length($fullname) > 150) {
                throw new RuntimeException('Họ tên không được để trống và tối đa 150 ký tự.');
            }
            if ($sumClassInput === '' || !ctype_digit($sumClassInput) || (int) $sumClassInput > 65535) {
                throw new RuntimeException('Tổng số lớp phải là số nguyên từ 0 đến 65535.');
            }

            $existingStatement = $db->prepare('SELECT IDGV, Image FROM GiangVien WHERE IDGV = :id');
            $existingStatement->execute(['id' => $editingId !== '' ? $editingId : $id]);
            $existing = $existingStatement->fetch();
            if ($editingId !== '' && !$existing) {
                throw new RuntimeException('Giảng viên cần cập nhật không còn tồn tại.');
            }
            if ($editingId !== '' && $id !== $editingId) {
                $duplicateStatement = $db->prepare('SELECT 1 FROM GiangVien WHERE IDGV = :id');
                $duplicateStatement->execute(['id' => $id]);
                if ($duplicateStatement->fetchColumn()) {
                    throw new RuntimeException('Mã giảng viên này đã được sử dụng.');
                }
            }
            if ($editingId === '' && $existing) {
                throw new RuntimeException('Mã giảng viên này đã được sử dụng.');
            }

            $newImage = store_uploaded_image($_FILES['Image'] ?? []);
            $imagePath = $newImage ?? ($existing['Image'] ?? null);

            try {
                if ($editingId !== '') {
                    $statement = $db->prepare(
                        'UPDATE GiangVien SET IDGV = :id, Fullname = :fullname, Image = :image, SumClass = :sum_class WHERE IDGV = :editing_id'
                    );
                    $statement->execute([
                        'id' => $id,
                        'fullname' => $fullname,
                        'image' => $imagePath,
                        'sum_class' => (int) $sumClassInput,
                        'editing_id' => $editingId,
                    ]);
                    if ($newImage !== null && $existing['Image'] !== $newImage) {
                        remove_stored_image($existing['Image']);
                    }
                    set_flash('success', 'Đã cập nhật thông tin giảng viên.');
                } else {
                    $statement = $db->prepare(
                        'INSERT INTO GiangVien (IDGV, Fullname, Image, SumClass) VALUES (:id, :fullname, :image, :sum_class)'
                    );
                    $statement->execute([
                        'id' => $id,
                        'fullname' => $fullname,
                        'image' => $imagePath,
                        'sum_class' => (int) $sumClassInput,
                    ]);
                    set_flash('success', 'Đã thêm giảng viên mới.');
                }
            } catch (Throwable $exception) {
                if ($newImage !== null) {
                    remove_stored_image($newImage);
                }
                throw $exception;
            }

            redirect_to();
        }

        if ($action === 'delete') {
            $id = trim((string) ($_POST['IDGV'] ?? ''));
            $statement = $db->prepare('SELECT Image FROM GiangVien WHERE IDGV = :id');
            $statement->execute(['id' => $id]);
            $imagePath = $statement->fetchColumn();

            $deleteStatement = $db->prepare('DELETE FROM GiangVien WHERE IDGV = :id');
            $deleteStatement->execute(['id' => $id]);
            if ($deleteStatement->rowCount() > 0) {
                remove_stored_image(is_string($imagePath) ? $imagePath : null);
                set_flash('success', 'Đã xóa giảng viên.');
            } else {
                set_flash('error', 'Không tìm thấy giảng viên cần xóa.');
            }
            redirect_to();
        }

        throw new RuntimeException('Thao tác không hợp lệ.');
    } catch (RuntimeException $exception) {
        set_flash('error', $exception->getMessage());
        redirect_to();
    } catch (PDOException $exception) {
        error_log('QLHocPhan database operation failed: ' . $exception->getMessage());
        set_flash('error', 'Không thể lưu thay đổi. Hãy kiểm tra dữ liệu và thử lại.');
        redirect_to();
    } catch (Throwable $exception) {
        error_log('QLHocPhan request failed: ' . $exception->getMessage());
        set_flash('error', 'Đã xảy ra lỗi khi xử lý yêu cầu. Vui lòng thử lại.');
        redirect_to();
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim((string) ($_GET['q'] ?? ''));
$editing = null;
$instructors = [];
$stats = ['total' => 0, 'classes' => 0];
$loadError = null;

if ($db instanceof PDO) {
    try {
        $stats = $db->query(
            'SELECT COUNT(*) AS total, COALESCE(SUM(SumClass), 0) AS classes FROM GiangVien'
        )->fetch();

        if ($search !== '') {
            $statement = $db->prepare(
                'SELECT IDGV, Fullname, Image, SumClass FROM GiangVien
                 WHERE IDGV LIKE :term OR Fullname LIKE :term
                 ORDER BY Fullname ASC'
            );
            $statement->execute(['term' => '%' . $search . '%']);
            $instructors = $statement->fetchAll();
        } else {
            $instructors = $db->query(
                'SELECT IDGV, Fullname, Image, SumClass FROM GiangVien ORDER BY Fullname ASC'
            )->fetchAll();
        }

        $editId = trim((string) ($_GET['edit'] ?? ''));
        if ($editId !== '') {
            $statement = $db->prepare(
                'SELECT IDGV, Fullname, Image, SumClass FROM GiangVien WHERE IDGV = :id'
            );
            $statement->execute(['id' => $editId]);
            $editing = $statement->fetch() ?: null;
            if ($editing === null) {
                $flash = ['type' => 'error', 'message' => 'Không tìm thấy giảng viên cần chỉnh sửa.'];
            }
        }
    } catch (PDOException $exception) {
        error_log('QLHocPhan data loading failed: ' . $exception->getMessage());
        $loadError = 'Không thể tải dữ liệu giảng viên. Hãy kiểm tra bảng GiangVien trong cơ sở dữ liệu.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Quản lý giảng viên | QL Học phần</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php" aria-label="QL Học phần - Trang chủ">
        <span class="brand-mark">Q</span>
        <span>QL HỌC PHẦN<small>HỆ THỐNG QUẢN LÝ ĐÀO TẠO</small></span>
    </a>
    <span class="topbar-label"><span class="status-dot"></span> Cổng quản trị</span>
</header>

<main class="page-shell">
    <section class="page-heading">
        <div>
            <p class="eyebrow">DỮ LIỆU ĐÀO TẠO <span>/</span> GIẢNG VIÊN</p>
            <h1>Quản lý giảng viên</h1>
            <p class="subtitle">Theo dõi hồ sơ và khối lượng giảng dạy của đội ngũ giảng viên.</p>
        </div>
        <a class="button button-primary heading-button" href="#form-panel">＋ <span>Thêm giảng viên</span></a>
    </section>

    <?php if ($flash): ?>
        <div class="notice notice-<?= escape($flash['type'] === 'success' ? 'success' : 'error') ?>" role="status">
            <span><?= escape($flash['message']) ?></span>
            <button type="button" class="notice-close" aria-label="Đóng thông báo">×</button>
        </div>
    <?php endif; ?>
    <?php if ($databaseError || $loadError): ?>
        <div class="notice notice-error" role="alert"><?= escape($databaseError ?? $loadError) ?></div>
    <?php endif; ?>

    <section class="stat-grid" aria-label="Thống kê">
        <article class="stat-card">
            <span class="stat-icon icon-purple">♙</span>
            <div><p>Tổng giảng viên</p><strong><?= number_format((int) $stats['total']) ?></strong></div>
            <span class="stat-caption">Hồ sơ đang quản lý</span>
        </article>
        <article class="stat-card">
            <span class="stat-icon icon-blue">▤</span>
            <div><p>Tổng số lớp</p><strong><?= number_format((int) $stats['classes']) ?></strong></div>
            <span class="stat-caption">Theo phân công giảng dạy</span>
        </article>
        <article class="stat-card stat-card-note">
            <span class="stat-icon icon-green">✓</span>
            <div><p>Trạng thái hệ thống</p><strong class="online-label"><?= $db instanceof PDO ? 'Đang hoạt động' : 'Chưa kết nối' ?></strong></div>
            <span class="stat-caption">Cơ sở dữ liệu QLHocPhan</span>
        </article>
    </section>

    <section class="content-panel" id="form-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">HỒ SƠ GIẢNG VIÊN</p>
                <h2><?= $editing ? 'Cập nhật thông tin' : 'Thêm giảng viên mới' ?></h2>
            </div>
            <?php if ($editing): ?>
                <a class="button button-quiet" href="index.php">Hủy chỉnh sửa</a>
            <?php endif; ?>
        </div>
        <form class="instructor-form" method="post" enctype="multipart/form-data" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="editing_id" value="<?= escape($editing['IDGV'] ?? '') ?>">
            <div class="form-field">
                <label for="idgv">Mã giảng viên <span class="required">*</span></label>
                <input id="idgv" name="IDGV" type="text" required maxlength="20" pattern="[A-Za-z0-9_-]{2,20}"
                       placeholder="Ví dụ: GV001" value="<?= escape($editing['IDGV'] ?? '') ?>">
                <small>2–20 ký tự, gồm chữ, số, dấu gạch ngang hoặc gạch dưới.</small>
            </div>
            <div class="form-field">
                <label for="fullname">Họ và tên <span class="required">*</span></label>
                <input id="fullname" name="Fullname" type="text" required maxlength="150"
                       placeholder="Nhập họ và tên giảng viên" value="<?= escape($editing['Fullname'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label for="sumclass">Tổng số lớp <span class="required">*</span></label>
                <input id="sumclass" name="SumClass" type="number" required min="0" max="65535"
                       placeholder="0" value="<?= escape(isset($editing['SumClass']) ? (string) $editing['SumClass'] : '') ?>">
            </div>
            <div class="form-field photo-field">
                <label for="image">Ảnh đại diện</label>
                <div class="file-control">
                    <input id="image" name="Image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span class="file-hint" id="file-hint"><?= $editing && $editing['Image'] ? 'Đã có ảnh — chọn ảnh mới để thay thế' : 'JPG, PNG, WEBP hoặc GIF · tối đa 5 MB' ?></span>
                </div>
                <?php if ($editing && $editing['Image']): ?>
                    <img class="current-image" src="<?= escape($editing['Image']) ?>" alt="Ảnh hiện tại của <?= escape($editing['Fullname']) ?>">
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button class="button button-primary" type="submit"><?= $editing ? 'Lưu thay đổi' : '＋ Thêm giảng viên' ?></button>
                <?php if ($editing): ?><a class="button button-quiet" href="index.php">Hủy</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="content-panel directory-panel">
        <div class="directory-heading">
            <div>
                <p class="eyebrow">DANH SÁCH</p>
                <h2>Đội ngũ giảng viên <span class="result-count"><?= count($instructors) ?></span></h2>
            </div>
            <form class="search-form" method="get" action="index.php" role="search">
                <label class="sr-only" for="search">Tìm theo mã hoặc họ tên</label>
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input id="search" type="search" name="q" value="<?= escape($search) ?>" placeholder="Tìm mã hoặc họ tên...">
                <button type="submit">Tìm kiếm</button>
            </form>
        </div>

        <?php if ($instructors): ?>
            <div class="instructor-grid">
                <?php foreach ($instructors as $instructor): ?>
                    <article class="instructor-card">
                        <div class="card-top">
                            <?php if ($instructor['Image']): ?>
                                <img class="avatar" src="<?= escape($instructor['Image']) ?>" alt="Ảnh <?= escape($instructor['Fullname']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="avatar avatar-placeholder" aria-hidden="true"><?= escape(function_exists('mb_substr') ? mb_substr($instructor['Fullname'], 0, 1, 'UTF-8') : substr($instructor['Fullname'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <span class="id-badge"><?= escape($instructor['IDGV']) ?></span>
                        </div>
                        <h3><?= escape($instructor['Fullname']) ?></h3>
                        <p class="card-role">Giảng viên</p>
                        <div class="class-count"><span>▤</span><strong><?= number_format((int) $instructor['SumClass']) ?></strong><span>lớp giảng dạy</span></div>
                        <div class="card-actions">
                            <a class="button button-small button-edit" href="index.php?edit=<?= rawurlencode($instructor['IDGV']) ?>#form-panel">Chỉnh sửa</a>
                            <form method="post" action="index.php" class="delete-form" data-name="<?= escape($instructor['Fullname']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="IDGV" value="<?= escape($instructor['IDGV']) ?>">
                                <button class="button button-small button-delete" type="submit" aria-label="Xóa <?= escape($instructor['Fullname']) ?>">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">♙</span>
                <h3><?= $search !== '' ? 'Không tìm thấy giảng viên' : 'Chưa có dữ liệu giảng viên' ?></h3>
                <p><?= $search !== '' ? 'Thử tìm bằng mã giảng viên hoặc từ khóa khác.' : 'Thêm giảng viên đầu tiên bằng biểu mẫu phía trên.' ?></p>
                <?php if ($search !== ''): ?><a class="button button-quiet" href="index.php">Xóa bộ lọc</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
    <footer class="page-footer">QL Học phần <span>·</span> Quản lý giảng viên</footer>
</main>
<script src="assets/app.js" defer></script>
</body>
</html>
