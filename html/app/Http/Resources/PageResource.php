<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Page model into the API response.
 */
class PageResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   *
   * @param  Request  $request
   * @return array[]
   */
  public function toArray(Request $request): array
  {
    return [
      'page' => [
        'id' => $this->id,
        'slug' => $this->slug,
        'title' => $this->title,
        'content' => $this->content,
        'meta_title' => $this->meta_title,
        'meta_description' => $this->meta_description,
        // Add if you added this field:
        // 'meta_keywords'  => $this->meta_keywords,
        'updated_at' => optional($this->updated_at)?->toAtomString(),
      ],
    ];
  }
}
