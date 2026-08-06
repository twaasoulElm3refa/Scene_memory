<?php

namespace App\Repositories\Contracts\Tags;

interface TagRepositoryInterface
{
    public function getAllTags();
    public function paginated($perPage = 30);
    public function getTagById($id);
    public function createTag(array $data);
    public function updateTag($id, array $data);
    public function deleteTag($id);
}
