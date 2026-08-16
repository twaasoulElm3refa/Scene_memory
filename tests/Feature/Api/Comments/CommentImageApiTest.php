<?php

namespace Tests\Feature\Api\Comments;

use App\Jobs\TranslateCommentJob;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentImageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake([TranslateCommentJob::class]);

        $this->user = User::factory()->create(['role' => 'user']);
        $this->event = Events::create([
            'user_id' => $this->user->id,
            'title' => 'Comment image event',
            'description' => 'Event used by comment image tests.',
            'slug' => 'comment-image-event',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
        $this->withHeader('Accept', 'application/json');
    }

    public function test_text_only_comments_remain_supported(): void
    {
        $response = $this->postJson($this->createUrl(), [
            'comment' => 'Text only comment',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.comment', 'Text only comment')
            ->assertJsonCount(0, 'data.images');

        $this->assertDatabaseHas('comments', [
            'event_id' => $this->event->id,
            'user_id' => $this->user->id,
            'comment' => 'Text only comment',
        ]);
    }

    public function test_comment_can_store_one_image_and_return_it_from_comment_apis(): void
    {
        $response = $this->post($this->createUrl(), [
            'comment' => 'Comment with one image',
            'images' => [$this->png('photo.jpg')],
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.images')
            ->assertJsonStructure([
                'data' => [
                    'images' => [['id', 'path', 'sort_order', 'url']],
                ],
            ]);

        $path = $response->json('data.images.0.path');
        Storage::disk('public')->assertExists($path);

        $this->getJson("/api/v1/comments/{$this->event->slug}")
            ->assertOk()
            ->assertJsonPath('data.data.0.images.0.path', $path);

        $this->getJson("/api/v1/events/{$this->event->slug}/single/get")
            ->assertOk()
            ->assertJsonPath('data.comments.0.images.0.path', $path);
    }

    public function test_comment_can_store_two_images_in_order(): void
    {
        $response = $this->post($this->createUrl(), [
            'comment' => 'Comment with two images',
            'images' => [
                $this->png('first.png'),
                $this->png('second.webp'),
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.sort_order', 0)
            ->assertJsonPath('data.images.1.sort_order', 1);

        $this->assertDatabaseCount('comment_images', 2);
    }

    public function test_more_than_two_images_are_rejected(): void
    {
        $response = $this->post($this->createUrl(), [
            'comment' => 'Too many images',
            'images' => [
                $this->png('first.png'),
                $this->png('second.png'),
                $this->png('third.png'),
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('comment_images', 0);
    }

    public function test_svg_images_are_rejected(): void
    {
        $response = $this->post($this->createUrl(), [
            'comment' => 'Invalid image type',
            'images' => [UploadedFile::fake()->createWithContent(
                'unsafe.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"></svg>'
            )],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_images_larger_than_five_megabytes_are_rejected(): void
    {
        $oversizedPng = str_pad($this->pngBytes(), (5 * 1024 * 1024) + 1, "\0");

        $response = $this->post($this->createUrl(), [
            'comment' => 'Image is too large',
            'images' => [UploadedFile::fake()->createWithContent('large.png', $oversizedPng)],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_deleting_a_comment_deletes_image_rows_and_files(): void
    {
        $createResponse = $this->post($this->createUrl(), [
            'comment' => 'Delete this comment',
            'images' => [$this->png('delete-me.png')],
        ])->assertOk();

        $commentId = $createResponse->json('data.id');
        $path = $createResponse->json('data.images.0.path');

        $this->deleteJson("/api/v1/comments/{$commentId}/delete")->assertOk();

        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
        $this->assertDatabaseMissing('comment_images', ['comment_id' => $commentId]);
        Storage::disk('public')->assertMissing($path);
    }

    private function createUrl(): string
    {
        return "/api/v1/comments/{$this->event->id}/create";
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes());
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
    }
}
