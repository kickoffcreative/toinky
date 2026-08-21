<?php
final class StoryReasonerFactory
{
    public static function make(): StoryReasoner
    {
        $provider=strtolower((string)AiInteractionLogger::setting('provider','deterministic'));
        return match($provider){'ollama'=>new OllamaStoryReasoner(),default=>new DeterministicStoryReasoner()};
    }

    public static function withFallback(): StoryReasoner
    {
        $primary=self::make();
        if($primary instanceof DeterministicStoryReasoner)return$primary;
        return new class($primary) implements StoryReasoner {
            public function __construct(private StoryReasoner $primary){}
            public function selectOption(array $context,array $options): array
            {
                try{return$this->primary->selectOption($context,$options);}catch(Throwable$e){$fallback=new DeterministicStoryReasoner();$result=$fallback->selectOption($context,$options);$result['fallback_used']=true;$result['fallback_error']=$e->getMessage();return$result;}
            }
        };
    }
}
