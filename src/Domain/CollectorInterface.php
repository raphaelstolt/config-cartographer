<?php

declare(strict_types=1);

namespace ConfigCartographer\Domain;

/**
 * Interface for collectors that discover configuration relationships.
 * Each collector returns an AnalysisResult containing graph fragments and diagnostics.
 */
interface CollectorInterface
{
    /**
     * Collect configuration relationships from the given project path.
     *
     * @param string $projectRoot The absolute path to the project root
     * @return AnalysisResult Graph fragment and any diagnostics
     */
    public function collect(string $projectRoot): AnalysisResult;
}
