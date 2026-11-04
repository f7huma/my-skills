<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LegalCase;
use App\Services\Files\FakeScanner;
use Database\Seeders\DemoCasesSeeder;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FilesTest extends TestCase
{
    private function upload(UploadedFile $file, string $category = 'other')
    {
        $case = LegalCase::first();

        return $this->actingAs($this->lawyer())->post(route('documents.store'), [
            'owner_type' => 'case', 'owner_id' => $case->id, 'file' => $file, 'category' => $category,
        ]);
    }

    public function test_valid_pdf_is_encrypted_at_rest_scanned_and_downloadable(): void
    {
        $this->demo();
        $this->upload(DemoCasesSeeder::fakePdf('Plain text marker XYZ'))->assertSessionHasNoErrors();
        $doc = Document::latest('id')->first();
        $this->assertSame('clean', $doc->scan_status);
        $raw = file_get_contents(config('attachments.root').'/'.$doc->storage_path);
        $this->assertStringNotContainsString('Plain text marker', $raw, 'الملف غير مشفر على القرص');
        $this->assertStringNotContainsString('%PDF', $raw);
        $resp = $this->actingAs($this->lawyer())->get('/files/'.$doc->uuid);
        $resp->assertOk();
        $this->assertStringContainsString('Plain text marker XYZ', $resp->streamedContent());
        $this->assertStringContainsString('sandbox', $resp->headers->get('Content-Security-Policy'));
    }

    public function test_rejects_extension_signature_mismatch_and_dangerous_content(): void
    {
        $this->demo();
        $before = Document::count();
        $this->upload(UploadedFile::fake()->createWithContent('run.exe', 'MZ...'))->assertSessionHasErrors('file');
        $this->upload(UploadedFile::fake()->createWithContent('fake.pdf', '<html><script>alert(1)</script></html>'))->assertSessionHasErrors('file');
        $this->upload(UploadedFile::fake()->createWithContent('active.pdf', "%PDF-1.4\n1 0 obj<</OpenAction<</S/JavaScript/JS(app.alert(1))>>>>endobj\n%%EOF"))->assertSessionHasErrors('file');
        $this->upload(UploadedFile::fake()->createWithContent('img.png', 'not really a png'))->assertSessionHasErrors('file');
        $this->upload(UploadedFile::fake()->createWithContent('page.html', '<html></html>'))->assertSessionHasErrors('file');
        $this->assertSame($before, Document::count());
    }

    public function test_macro_enabled_office_files_are_rejected(): void
    {
        $this->demo();
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->addFromString('word/vbaProject.bin', 'macro');
        $zip->close();
        $this->upload(new UploadedFile($path, 'macro.docx', null, null, true))->assertSessionHasErrors('file');
    }

    public function test_infected_file_is_blocked_and_content_destroyed(): void
    {
        $this->demo();
        $eicar = "%PDF-1.4\n% X5O!P%@AP[4\\PZX54(P^)7CC)7}\$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!\$H+H*\n%%EOF";
        $this->upload(UploadedFile::fake()->createWithContent('eicar.pdf', $eicar));
        $doc = Document::latest('id')->first();
        $this->assertSame('infected', $doc->scan_status);
        $this->assertFileDoesNotExist(config('attachments.root').'/'.$doc->storage_path);
        $this->actingAs($this->lawyer())->get('/files/'.$doc->uuid)->assertNotFound();
    }

    public function test_file_stays_blocked_while_scanner_is_down_then_released(): void
    {
        $this->demo();
        FakeScanner::$down = true;
        try {
            $this->upload(DemoCasesSeeder::fakePdf('Scanner down'));
            $doc = Document::latest('id')->first();
            $this->assertContains($doc->scan_status, ['pending', 'error']);
            $this->actingAs($this->lawyer())->get('/files/'.$doc->uuid)->assertNotFound();
        } finally {
            FakeScanner::$down = false;
        }
        $this->artisan('files:rescan-pending');
        $doc->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
        $this->artisan('files:rescan-pending');
        $this->assertSame('clean', $doc->fresh()->scan_status);
        $this->actingAs($this->lawyer())->get('/files/'.$doc->uuid)->assertOk();
    }

    public function test_storage_paths_are_not_browsable(): void
    {
        $this->demo();
        $doc = Document::first();
        $this->get('/storage/'.$doc->storage_path)->assertNotFound();
        $this->get('/'.$doc->storage_path)->assertNotFound();
        $this->assertStringNotContainsString(public_path(), config('attachments.root'));
    }

    public function test_key_rotation_reencrypts_files(): void
    {
        $this->demo();
        $doc = Document::where('scan_status', 'clean')->first();
        $oldKey = config('attachments.key');
        $newKey = \App\Services\Files\FileCipher::generateKey();
        config(['attachments.key' => $newKey, 'attachments.previous_keys' => [$oldKey]]);
        app()->forgetInstance(\App\Services\Files\FileCipher::class);
        app()->forgetInstance(\App\Services\Files\FileVault::class);
        $this->artisan('files:rotate-key')->assertSuccessful();
        $this->assertNotSame($doc->key_id, $doc->fresh()->key_id);
        config(['attachments.previous_keys' => []]);
        app()->forgetInstance(\App\Services\Files\FileCipher::class);
        $this->actingAs($this->lawyer())->get('/files/'.$doc->uuid)->assertOk();
    }
}
