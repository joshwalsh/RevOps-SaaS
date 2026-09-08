---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## Livewire file uploads: always pass an explicit disk to store()
`TemporaryUploadedFile::store($path)` without an explicit disk defaults to the upload's own *temporary* disk (in tests: `tmp-for-tests`, from `FileUploadConfiguration::disk()`), not `filesystems.default`. If you then read the file back via `Storage::path($path)` (which resolves the default disk), the path won't exist there — the file silently landed elsewhere.

Always call `$file->store($path, 'local')` (or whichever disk you intend), and read it back via `Storage::disk('local')->path(...)`, matching the disk explicitly on both ends. This bit the People CSV import feature (`resources/views/livewire/pages/organizations/people/import.blade.php`) — tests failed with "no such file" until the disk was pinned explicitly.
