<?php
declare(strict_types=1);

/**
 * BlockCandidateService
 *
 * Manages candidates per position.
 * Handles photo upload with MIME validation and random filename to prevent
 * directory traversal and overwrite attacks.
 */
class BlockCandidateService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** All candidates for a position. */
    public function getByPosition(int $positionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM block_candidates WHERE position_id = ? ORDER BY full_name ASC'
        );
        $stmt->execute([$positionId]);
        return $stmt->fetchAll();
    }

    /** All active candidates for a position (used in ballot generation). */
    public function getActiveByPosition(int $positionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM block_candidates
             WHERE position_id = ? AND is_active = 1
             ORDER BY full_name ASC'
        );
        $stmt->execute([$positionId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM block_candidates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $positionId, array $data, ?array $uploadedFile = null): int
    {
        $photoPath = $this->handleUpload($uploadedFile);

        $stmt = $this->db->prepare(
            'INSERT INTO block_candidates
             (position_id, full_name, party, platform, photo_path, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $positionId,
            $data['full_name'],
            $data['party']    ?? null,
            $data['platform'] ?? null,
            $photoPath,
            !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data, ?array $uploadedFile = null): void
    {
        $candidate = $this->getById($id);
        if (!$candidate) throw new RuntimeException('Candidate not found.');

        // Only replace photo if a new one was uploaded
        $photoPath = $this->handleUpload($uploadedFile) ?? $candidate['photo_path'];

        $stmt = $this->db->prepare(
            'UPDATE block_candidates
             SET full_name=?, party=?, platform=?, photo_path=?, is_active=?
             WHERE id=?'
        );
        $stmt->execute([
            $data['full_name'],
            $data['party']    ?? null,
            $data['platform'] ?? null,
            $photoPath,
            !empty($data['is_active']) ? 1 : 0,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $candidate = $this->getById($id);
        if ($candidate && $candidate['photo_path']) {
            $full = UPLOAD_DIR . basename($candidate['photo_path']);
            if (is_file($full)) unlink($full);
        }
        $this->db->prepare('DELETE FROM block_candidates WHERE id = ?')->execute([$id]);
    }

    public function toggleActive(int $id): void
    {
        $this->db->prepare(
            'UPDATE block_candidates SET is_active = 1 - is_active WHERE id = ?'
        )->execute([$id]);
    }

    /**
     * Validate and store an uploaded photo.
     * Returns the relative web path or null if no file was provided.
     * Throws RuntimeException on invalid file.
     */
    private function handleUpload(?array $file): ?string
    {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error code: ' . $file['error']);
        }

        if ($file['size'] > UPLOAD_MAX_BYTES) {
            throw new RuntimeException('Photo exceeds 2 MB limit.');
        }

        // Use finfo for real MIME detection — not the browser-supplied type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if (!in_array($mime, UPLOAD_ALLOWED, true)) {
            throw new RuntimeException('Only JPG, PNG, and WebP photos are allowed.');
        }

        $ext      = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        };
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Failed to save uploaded photo.');
        }

        return 'uploads/candidates/' . $filename;
    }
}
