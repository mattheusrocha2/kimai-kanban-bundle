<?php

namespace KimaiPlugin\KanbanBundle\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Reads/writes task attachment files on disk, under var/kanban/attachments —
 * outside the public web root, so an image is only ever reachable through
 * TaskController's authenticated "file" route, never a guessable public URL.
 */
class TaskAttachmentStorage
{
    /** Only image types may be attached; screenshots/photos, nothing executable. */
    public const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    private readonly string $directory;

    public function __construct(ParameterBagInterface $params)
    {
        $this->directory = rtrim((string) $params->get('kernel.project_dir'), '/') . '/var/kanban/attachments';
    }

    public function save(UploadedFile $file): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('kanban.attachment.storage_unavailable');
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        $file->move($this->directory, $filename);

        return $filename;
    }

    public function getPath(string $filename): string
    {
        return $this->directory . '/' . $filename;
    }

    public function delete(string $filename): void
    {
        $path = $this->getPath($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
