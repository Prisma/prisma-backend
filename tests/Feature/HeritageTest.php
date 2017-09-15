<?php

namespace Tests\Feature;

use App\User;
use App\Story;
use App\Album;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class HeritageTest extends TestCase
{
    private $baseEndpoint = 'v1/album/{albumId}/heritage';
    private $endpoint;
    private $specificEndpoint;

    private $defaultAlbumId;
    private $testHeritageId;
    private $baseObject = [
        'id' => null,
        'description' => 'A description',
        'happened_at' => null,
        'album_id' => 1,
        'asset_name' => null,
        'asset_type' => 'image'
    ];

    private function getEndpointWithAlbumId($albumId = null)
    {
        return str_replace('{albumId}', $albumId ?: $this->defaultAlbumId, $this->baseEndpoint);
    }

    public function loginAsSuperAdmin()
    {
        $user = factory(User::class)->create(['user_type' => 'superadmin']);
        $this->authenticate($user);
    }

    public function setUp()
    {
        parent::setUp();
        $user = factory(User::class)->create(['user_type' => 'superadmin']);
        $this->authenticate($user);
        $defaultAlbum = Album::with('heritage')->get()
                            ->where('patient_id', '=', null)->values()->first();
        $this->defaultAlbumId = $defaultAlbum->id;
        $this->testHeritageId = $defaultAlbum->heritage()->first()->id;
        $this->baseObject['album_id'] = $this->defaultAlbumId;
        $this->endpoint = $this->getEndpointWithAlbumId();
        $this->specificEndpoint = "$this->endpoint/$this->testHeritageId";
    }

    public function testResourceIsProtected()
    {
        $headers = $this->headers;
        unset($headers['HTTP_Authorization']);
        $this->getJson($this->specificEndpoint, $headers)
            ->assertStatus(401);
    }

    public function testIndexHeritage()
    {
        $this->getJson($this->endpoint, $this->headers)
             ->assertJsonStructure([
                 'meta' => $this->metaResponseStructure,
                 'response' => [ '*' => array_keys($this->baseObject) ]
             ])
             ->assertStatus(200);
    }

    public function testIndexHeritageWithInvalidAlbumId()
    {
        $endpoint = $this->getEndpointWithAlbumId(999) . '/' . $this->testHeritageId;
        $this->getJson($endpoint, $this->headers)
            ->assertJsonStructure($this->exceptionResponseStructure)
            ->assertStatus(400);
    }

    public function testAllUserTypesCanIndexHeritage()
    {
        foreach ($this->userTypes as $userType) {
            $user = factory(User::class)->create(['user_type' => $userType]);
            $this->authenticate($user);
            $this->testIndexHeritage();
        }
    }

    public function testGetHeritage($location = null)
    {
        $endpoint = $this->specificEndpoint;
        if ($location) {
            $endpoint = $this->parseResourceLocation($location);
        }
        $this->getJson($endpoint, $this->headers)
            ->assertJsonStructure([
                'meta' => $this->metaResponseStructure,
                'response' => array_keys($this->baseObject)
            ])
            ->assertStatus(200);
    }

    public function testGetHeritageWithInvalidAlbumId()
    {
        $endpoint = $this->getEndpointWithAlbumId(999) . '/' . $this->testHeritageId;
        $this->getJson($endpoint, $this->headers)
            ->assertJsonStructure($this->exceptionResponseStructure)
            ->assertStatus(400);
    }

    public function testGetHeritageWithInvalidHeritageId()
    {
        $endpoint = $this->endpoint . '/' . 0;
        $this->getJson($endpoint, $this->headers)
            ->assertJsonStructure($this->exceptionResponseStructure)
            ->assertStatus(400);
    }

    public function testAllUserTypesCanGetHeritage()
    {
        foreach ($this->userTypes as $userType) {
            $user = factory(User::class)->create(['user_type' => $userType]);
            $this->authenticate($user);
            $this->testGetHeritage();
        }
    }

    public function testCreateHeritage()
    {
        $baseObject = $this->baseObject;
        unset($baseObject['asset_name']);
        unset($baseObject['asset_type']);
        $body = [ 'description' => str_random(16), 'album_id' => $this->defaultAlbumId ];
        $response = $this->postJson($this->endpoint, $body, $this->headers)
            ->assertJsonStructure([
                'meta' => $this->metaCreatedResponseStructure,
                'response' => array_keys($baseObject)
            ])
            ->assertStatus(201)
            ->getData();
        $this->testGetHeritage($response->meta->location);
    }

    public function testOnlySuperAdminCanCreateHeritage()
    {
        // copy user types without superadmin
        $userTypes = array_diff($this->userTypes, ['superadmin']);
        foreach ($userTypes as $userType) {
            $user = factory(User::class)->create(['user_type' => $userType]);
            $this->authenticate($user);
            $this->postJson($this->endpoint, ['title' => str_random(16)], $this->headers)
                 ->assertStatus(403);
        }
    }

    public function testHeritageCreationForNonDefaultAlbum()
    {
        $album = factory(Album::class)->create(['patient_id' => $this->testPatientId]);
        $endpoint = $this->endpoint . '/' . $album->id;
        $this->postJson($this->endpoint, ['title' => $album->title], $this->headers)
             ->assertStatus(400);
    }
}
