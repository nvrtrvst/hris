import React from 'react';
import { AlertCircle, RefreshCw } from 'lucide-react';

/**
 * Error Boundary Component
 * Catches JavaScript errors in child component trees and displays fallback UI
 */
export class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({
            error,
            errorInfo,
        });

        console.error('Error caught by ErrorBoundary:', error, errorInfo);

        // Send error to monitoring service (OPTIONAL)
        // const errorMessage = this.state?.error?.toString();
        // const stackTrace = this.state?.errorInfo?.componentStack;
        // sendErrorToMonitoring(errorMessage, stackTrace);
    }

    tryRecover = () => {
        this.setState({ hasError: false, error: null, errorInfo: null });
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
                    <div className="max-w-md w-full space-y-6 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-rose-100">
                            <AlertCircle className="h-8 w-8 text-rose-600" />
                        </div>

                        <h2 className="text-2xl font-bold text-slate-900">
                            Terjadi Kesalahan
                        </h2>

                        <p className="text-slate-600">
                            Maaf, terjadi kesalahan yang tidak terduga. Coba muat ulang halaman.
                        </p>

                        <div className="flex flex-col space-y-3 sm:flex-row sm:justify-center sm:space-y-0 sm:space-x-3">
                            <button
                                onClick={this.tryRecover}
                                className="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Coba Ulang
                            </button>

                            <button
                                onClick={() => window.location.reload()}
                                className="inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Muat Ulang Halaman
                            </button>
                        </div>

                        {process.env.NODE_ENV === 'development' && this.state.error && (
                            <details className="mt-6">
                                <summary className="cursor-pointer text-sm font-medium text-slate-700 hover:text-slate-900">
                                    Detail Error (Development)
                                </summary>
                                <div className="mt-3 p-4 bg-red-50 rounded-lg text-left overflow-auto">
                                    <p className="text-xs font-mono text-red-800">
                                        {this.state.error.toString()}
                                    </p>
                                    {this.state.errorInfo && (
                                        <pre className="mt-2 text-xs font-mono text-red-700 whitespace-pre-wrap">
                                            {this.state.errorInfo.componentStack}
                                        </pre>
                                    )}
                                </div>
                            </details>
                        )}
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
