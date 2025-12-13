<?php

declare(strict_types=1);

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Log;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

/**
 * Service for parsing JSON responses from AI providers with error recovery
 */
class AiJsonParserService
{
    /**
     * Parse JSON using JSON Machine for robust handling of incomplete or malformed JSON
     *
     * @param  string  $jsonString  The potentially incomplete JSON string
     * @return array<string, mixed>|null The parsed JSON array or null if parsing fails
     */
    public function parse(string $jsonString): ?array
    {
        // First, try standard JSON decode for performance
        $decoded = json_decode($jsonString, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Log JSON error for debugging
        $jsonError = json_last_error_msg();
        Log::info('Standard JSON decode failed', [
            'error' => $jsonError,
            'first_100_chars' => substr($jsonString, 0, 100),
        ]);

        // Try to fix unescaped newlines (common with AI responses)
        $fixedJson = $this->tryFixUnescapedNewlines($jsonString);
        if ($fixedJson !== null) {
            return $fixedJson;
        }

        // Try to fix incomplete JSON by adding missing brackets
        $fixedJson = $this->tryFixIncompleteBrackets($jsonString);
        if ($fixedJson !== null) {
            return $fixedJson;
        }

        // Try JSON Machine for streaming parsing
        $result = $this->tryJsonMachine($jsonString);
        if ($result !== null) {
            return $result;
        }

        // Enhanced fallback: try to extract the message field
        return $this->tryExtractMessageField($jsonString);
    }

    /**
     * Try to fix JSON with unescaped newlines
     *
     * @return array<string, mixed>|null
     */
    private function tryFixUnescapedNewlines(string $jsonString): ?array
    {
        if (preg_match('/^\s*\{\s*"message"\s*:\s*"(.*)"\s*}\s*$/s', $jsonString, $matches)) {
            $messageContent = $matches[1];

            // Properly escape the message content for JSON
            $escapedMessage = json_encode($messageContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Rebuild the JSON with properly escaped content
            $fixedJson = '{"message":'.$escapedMessage.'}';

            $decoded = json_decode($fixedJson, true);
            if (is_array($decoded)) {
                Log::info('JSON parsed successfully after fixing unescaped newlines');

                return $decoded;
            }
        }

        return null;
    }

    /**
     * Try to fix incomplete JSON by adding missing brackets
     *
     * @return array<string, mixed>|null
     */
    private function tryFixIncompleteBrackets(string $jsonString): ?array
    {
        $cleanedJson = trim($jsonString);

        $openBraces = substr_count($cleanedJson, '{');
        $closeBraces = substr_count($cleanedJson, '}');
        $openBrackets = substr_count($cleanedJson, '[');
        $closeBrackets = substr_count($cleanedJson, ']');

        // Add missing closing braces/brackets
        $cleanedJson .= str_repeat(']', max(0, $openBrackets - $closeBrackets));
        $cleanedJson .= str_repeat('}', max(0, $openBraces - $closeBraces));

        $decoded = json_decode($cleanedJson, true);
        if (is_array($decoded)) {
            Log::info('JSON parsed successfully after bracket completion', [
                'added_brackets' => max(0, $openBrackets - $closeBrackets),
                'added_braces' => max(0, $openBraces - $closeBraces),
            ]);

            return $decoded;
        }

        return null;
    }

    /**
     * Try to parse using JSON Machine for streaming parsing
     *
     * @return array<string, mixed>|null
     */
    private function tryJsonMachine(string $jsonString): ?array
    {
        try {
            $cleanedJson = trim($jsonString);

            // Use JSON Machine with ExtJsonDecoder for better error handling
            $items = Items::fromString($cleanedJson, [
                'decoder' => new ExtJsonDecoder(true),
            ]);

            // Convert the iterator to an array
            $result = [];
            foreach ($items as $key => $value) {
                $result[$key] = $value;
            }

            if (! empty($result)) {
                Log::info('JSON parsed successfully with JSON Machine', [
                    'original_length' => strlen($jsonString),
                    'cleaned_length' => strlen($cleanedJson),
                ]);

                return $result;
            }
        } catch (Exception $e) {
            Log::warning('JSON Machine parsing failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Try to extract the message field with multiline support
     *
     * @return array<string, mixed>|null
     */
    private function tryExtractMessageField(string $jsonString): ?array
    {
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.|\\\\n|\\\\r)*)"/s', $jsonString, $matches)) {
            $escapedMessage = $matches[1];
            // Decode the JSON-escaped string
            $message = json_decode('"'.$escapedMessage.'"');
            if ($message !== null) {
                Log::warning('JSON recovered by extracting message field', [
                    'message_length' => strlen($message),
                ]);

                return ['message' => $message];
            }
        }

        return null;
    }
}
