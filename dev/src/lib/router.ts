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
    Routing.setBaseUrl(adminUrl);
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
    const tokenizedParams = Object.assign(params, { _token: this.token });

    return Routing.generate(route, tokenizedParams);
  }
}