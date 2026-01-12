import Routing from 'fos-routing';
import routes from '../fos_js_routes.json';

/* eslint-disable */
/**
 * Wraps FOSJsRoutingbundle with exposed routes.
 * To expose route add option `expose: true` in .yml routing config
 *
 * e.g.
 *
 * `my_route
 *    path: /my-path
 *    options:
 *      expose: true
 * And run `bin/console fos:js-routing:dump --format=json --target=modules/buckaroo3/dev`
 */
/* eslint-enable */
export default class Router {

 private token:string;

  constructor(adminUrl: string, token:string) {
    Routing.setData(routes);
    // Remove trailing slash from adminUrl to prevent double slashes
    const normalizedAdminUrl = adminUrl.replace(/\/+$/, '');
    Routing.setBaseUrl(normalizedAdminUrl);
    this.token = token;
    return this;
  }

  /**
   * Decorated "generate" method, with predefined security token in params
   *
   * @param route
   * @param params
   *
   * @returns {String}
   */
  generate(route: string, params: Record<string, unknown> = {}): string {
    // Only add token if it's not empty
    if (this.token && this.token.trim() !== '') {
      const tokenizedParams = Object.assign(params, { _token: this.token });
      return Routing.generate(route, tokenizedParams);
    }
    
    // If no token, generate URL without token parameter
    // The params might already have a token from the URL
    if (!params._token) {
      // Don't add empty token
      return Routing.generate(route, params);
    }
    
    return Routing.generate(route, params);
  }
}