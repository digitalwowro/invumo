import { useEffect } from 'react';

type DocumentEditorReports = {
    dirty: boolean;
    processing: boolean;
    lineCount: number;
    onDirtyChange?: (dirty: boolean) => void;
    onProcessingChange?: (processing: boolean) => void;
    onLineCountChange?: (count: number) => void;
};

export function useDocumentEditorReports(reports: DocumentEditorReports) {
    const {
        dirty,
        processing,
        lineCount,
        onDirtyChange,
        onProcessingChange,
        onLineCountChange,
    } = reports;

    useEffect(() => {
        onDirtyChange?.(dirty);
    }, [dirty, onDirtyChange]);

    useEffect(() => {
        onProcessingChange?.(processing);
    }, [processing, onProcessingChange]);

    useEffect(() => {
        onLineCountChange?.(lineCount);
    }, [lineCount, onLineCountChange]);
}
