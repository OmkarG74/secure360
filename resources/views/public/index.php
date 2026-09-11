<?php
/**
 * Public Landing Page View
 * Replicates Screenshot 1: Premium enterprise presentation with "Live Security Pulse"
 */
?>

<!-- Hero Section -->
<section style="padding: 4.5rem 1.5rem 3rem 1.5rem; text-align: center; max-width: 950px; margin: 0 auto;">
    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #eff6ff; border: 1px solid #dbeafe; color: #2563eb; font-size: 0.8125rem; font-weight: 600; padding: 0.35rem 0.9rem; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1.5rem;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #2563eb;"></span>
        Enterprise Security Operations Platform
    </div>
    
    <h1 style="font-size: 3rem; font-weight: 800; color: #0f172a; margin-bottom: 1.25rem; line-height: 1.15; letter-spacing: -0.02em;">
        Total Operational Visibility &amp; <br><span style="color: #2563eb;">Guard Force Management</span>
    </h1>
    
    <p style="font-size: 1.125rem; color: #475569; line-height: 1.6; margin-bottom: 2.25rem; max-width: 720px; margin-left: auto; margin-right: auto;">
        Secure360 seamlessly connects security agencies, multi-site enterprise clients, and mobile Flutter guards into one synchronized operational command center.
    </p>
    
    <div style="display: flex; gap: 1rem; justify-content: center; align-items: center; flex-wrap: wrap;">
        <a href="<?= url('/login') ?>" class="btn btn-primary" style="padding: 0.75rem 1.85rem; font-size: 1rem; font-weight: 600; text-decoration: none; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);">
            Sign in to Operations Portal
        </a>
        <a href="#live-pulse" class="btn btn-outline" style="padding: 0.75rem 1.5rem; font-size: 1rem; font-weight: 500; text-decoration: none; background: #fff;">
            Live Security Pulse
        </a>
    </div>
</section>

<!-- Live Security Pulse Component (Screenshot 1 Highlight) -->
<section id="live-pulse" style="max-width: 1050px; margin: 2rem auto 4rem auto; padding: 0 1.5rem;">
    <div class="card" style="border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); padding: 2rem; background: #ffffff;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 40px; height: 40px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);"></span>
                </div>
                <div>
                    <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a;">Live Security Pulse</h3>
                    <p style="font-size: 0.8125rem; color: #64748b;">Real-time ecosystem connectivity &amp; field synchronization</p>
                </div>
            </div>
            <span style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; font-weight: 600; color: #16a34a; background: #dcfce7; padding: 0.3rem 0.75rem; border-radius: 9999px;">
                Systems Operational
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.5rem;">
                    Database Engine
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">
                    secure360_v2
                </div>
                <p style="font-size: 0.75rem; color: #10b981; font-weight: 500;">
                    15 Tables Fully Active &amp; Scoped
                </p>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.5rem;">
                    Flutter Mobile API
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">
                    REST v1 Endpoints
                </div>
                <p style="font-size: 0.75rem; color: #2563eb; font-weight: 500;">
                    Bearer Auth &amp; JSON Payload Active
                </p>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.5rem;">
                    Multi-Tenant Isolation
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">
                    Strict Organization
                </div>
                <p style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                    Zero Data Leakage Boundary
                </p>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; margin-bottom: 0.5rem;">
                    Field Telemetry
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">
                    GPS &amp; Facial Check-in
                </div>
                <p style="font-size: 0.75rem; color: #10b981; font-weight: 500;">
                    Live Roster Synchronized
                </p>
            </div>

        </div>
    </div>
</section>

<!-- Modules Overview -->
<section style="background: #fff; border-top: 1px solid #e2e8f0; padding: 4rem 1.5rem;">
    <div style="max-width: 1100px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h2 style="font-size: 2rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">Engineered for High-Stakes Operations</h2>
            <p style="font-size: 0.9375rem; color: #64748b;">Every tier of the platform is designed around strict tenant security and field reliability.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
            
            <div class="card" style="margin-bottom: 0; padding: 2rem; border-color: #e2e8f0;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: #0f172a;">1 Client &rarr; Multiple Sites Hierarchy</h3>
                <p style="color: #475569; font-size: 0.875rem; line-height: 1.6;">
                    Manage corporate client accounts with unlimited physical posts, gates, and geofenced zones. Dynamically provision post locations with individual coordinates.
                </p>
            </div>

            <div class="card" style="margin-bottom: 0; padding: 2rem; border-color: #e2e8f0;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: #0f172a;">Guard Roster &amp; Mobile Provisioning</h3>
                <p style="color: #475569; font-size: 0.875rem; line-height: 1.6;">
                    Onboard guards with auto-generated badge codes, portrait uploads, and mobile passwords. Personnel can immediately log in on the Flutter mobile app.
                </p>
            </div>

            <div class="card" style="margin-bottom: 0; padding: 2rem; border-color: #e2e8f0;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: #0f172a;">Flutter Mobile REST Bridge</h3>
                <p style="color: #475569; font-size: 0.875rem; line-height: 1.6;">
                    Standardized JSON APIs with SHA-256 Bearer tokens. Flutter guards view their daily duties, submit GPS check-ins, and upload selfie verification photos.
                </p>
            </div>

        </div>
    </div>
</section>
