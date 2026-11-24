<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'description' => $this->description,
            'isbn' => $this->isbn,
            'price' => $this->price,
            'stock' => $this->stock,
            'cover_url' => $this->cover_url,
            'category_id' => $this->category_id,
            'author_id' => $this->author_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => $this->whenLoaded('category'),
            'author_model' => $this->whenLoaded('authorModel'),
            'reviews' => $this->whenLoaded('reviews', fn () => ReviewResource::collection($this->reviews)),
            'reviews_count' => $this->whenCounted('reviews'),
            'average_rating' => $this->when(
                array_key_exists('reviews_avg_rating', $this->resource->getAttributes()),
                fn () => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 2) : null
            ),
        ];
    }
}
