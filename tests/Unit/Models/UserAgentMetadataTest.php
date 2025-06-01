<?php

namespace Tests\Unit\Models;

use App\Models\UserAgentMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;

#[CoversClass(UserAgentMetadata::class)]
class UserAgentMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes()
    {
        $expectedFillable = [
            'user_agent_id',
            'is_bot',
        ];

        $model = new UserAgentMetadata;

        $this->assertEquals($expectedFillable, $model->getFillable());
    }

    public function test_timestamps_are_disabled()
    {
        $model = new UserAgentMetadata;

        $this->assertFalse($model->timestamps);
    }

    public function test_casts_is_bot_to_boolean()
    {
        $model = new UserAgentMetadata;
        $casts = $model->getCasts();

        $this->assertEquals('boolean', $casts['is_bot']);
    }

    public function test_belongs_to_user_agent_relationship()
    {
        $userAgent = UserAgent::factory()->create();
        $metadata = UserAgentMetadata::factory()->create([
            'user_agent_id' => $userAgent->id,
        ]);

        $this->assertInstanceOf(UserAgent::class, $metadata->userAgent);
        $this->assertEquals($userAgent->id, $metadata->userAgent->id);
    }

    public function test_can_create_user_agent_metadata()
    {
        $userAgent = UserAgent::factory()->create();

        $metadata = UserAgentMetadata::create([
            'user_agent_id' => $userAgent->id,
            'is_bot' => true,
        ]);

        $this->assertDatabaseHas('user_agent_metadata', [
            'id' => $metadata->id,
            'user_agent_id' => $userAgent->id,
            'is_bot' => true,
        ]);
    }

    public function test_is_bot_attribute_is_cast_to_boolean()
    {
        $userAgent = UserAgent::factory()->create();

        $metadata = UserAgentMetadata::create([
            'user_agent_id' => $userAgent->id,
            'is_bot' => 1, // Insert as integer
        ]);

        $metadata->refresh();

        $this->assertIsBool($metadata->is_bot);
        $this->assertTrue($metadata->is_bot);
    }

    public function test_can_create_non_bot_user_agent_metadata()
    {
        $userAgent = UserAgent::factory()->create();

        $metadata = UserAgentMetadata::create([
            'user_agent_id' => $userAgent->id,
            'is_bot' => false,
        ]);

        $this->assertDatabaseHas('user_agent_metadata', [
            'id' => $metadata->id,
            'user_agent_id' => $userAgent->id,
            'is_bot' => false,
        ]);

        $this->assertFalse($metadata->is_bot);
    }

    public function test_user_agent_id_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        UserAgentMetadata::create([
            'is_bot' => true,
        ]);
    }
}
